<?php
/**
 * Allow to detect and manage AdBlock
 * Date: 12/10/2019
 * Time: 11:34
 *
 * @doc         https://www.detectadblock.com/
 *
 * @author      clemdesign <contact@clemdesign.fr>
 * @copyright   2019, clemdesign
 * @license     http://opensource.org/licenses/MIT
 */


namespace Grav\Plugin;


use Grav\Common\Plugin;


/**
 * DetectAdblockPlugin.
 *
 * This plugin enables to use DetectAdblockPlugin inside a document
 * to be rendered by Grav.
 */
class DetectAdBlockPlugin extends Plugin
{

  /**
   * @return array
   *
   * The getSubscribedEvents() gives the core a list of events
   *     that the plugin wants to listen to. The key of each
   *     array section is the event that the plugin listens to
   *     and the value (in the form of an array) contains the
   *     callable (or function) as well as the priority. The
   *     higher the number the higher the priority.
   */
  public static function getSubscribedEvents()
  {

    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0],
      'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
    ];
  }

  /**
   * Initialize the plugin
   */
  public function onPluginsInitialized()
  {

    if (!$this->isAdmin() && $this->config->get('plugins.detect-adblock.enabled')) {
      $this->enable([
        'onPageInitialized' => ['onPageInitialized', -1],
        'onPageContentRaw' => ['onPageContentRaw', 0],
        'onPageContentProcessed' => ['onPageContentProcessed', 0]
      ]);
    }
  }

  /**
   * Function called on Grav Page initialized
   */
  public function onPageInitialized()
  {
    // Add Detection JS
    $inlineJs = 'var abDetected = !(document.getElementById(\'DeTEctAdBloCK\')!==null);';

    // Add Analytics JS
    if ($this->config->get('plugins.detect-adblock.ganalytics')) {
      $inlineJs .= 'if(typeof ga !==\'undefined\'){ga(\'send\',\'event\',\'Blocking Ads\',(abDetected?\'Yes\':\'No\'),{\'nonInteraction\':1});}';
      $inlineJs .= 'else if(typeof _gaq !==\'undefined\'){_gaq.push([\'_trackEvent\',\'Blocking Ads\',(abDetected?\'Yes\':\'No\'),undefined,undefined,true]);}';
    }

    // Manage Message
    if ($this->config->get('plugins.detect-adblock.message.enabled')) {


      //Manage Page Filter
      $filter_items = $this->config->get('plugins.detect-adblock.message.page_filter');
      $url = strtolower($this->cleanUrl($this->grav["uri"]->url()));

      $bDispMessage = false;
      if (is_array($filter_items) && (count($filter_items) > 0)) {
        if (!empty($url)) {
          //Look for url in filter list
          if (in_array($url, $filter_items)) $bDispMessage = true;
        }
      }

      if($bDispMessage) {

        $displayOnlyOneTimes = $this->config->get('plugins.detect-adblock.message.displayone');
        $blockVisitEnabled = $this->config->get('plugins.detect-adblock.blockvisit.enabled');
        $blockVisitId = $this->config->get('plugins.detect-adblock.blockvisit.idtoremove');

        $inlineJs .= 'if(document.getElementById(\'detect-adblock\')!==null){';

        //Manage display only one times
        if ($displayOnlyOneTimes && (!$blockVisitEnabled)) {
          $this->grav['assets']->add('plugin://detect-adblock/assets/js/cookies.js', null, true, null, 'bottom');
          $inlineJs .= 'if(abDetected && (getCookie("detect-adblock")!="true")){document.getElementById(\'detect-adblock\').style.display=\'block\';}';
        } else {
          $inlineJs .= 'if(abDetected){document.getElementById(\'detect-adblock\').style.display=\'block\';}';
        }

        //Function to hide message
        if (!$blockVisitEnabled) {
          $inlineJs .= 'function dabHide(){document.getElementById(\'detect-adblock\').style.display=\'none\';';
          if ($displayOnlyOneTimes) {
            $inlineJs .= 'setCookie("detect-adblock","true",1)';
          }
          $inlineJs .= '}';
        } else {
          $inlineJs .= 'function dabHide(){}';
        }

        //Block Visit operation
        if ($blockVisitEnabled) {
          $inlineJs .= 'if((document.getElementById(\'' . $blockVisitId . '\')!==null) && abDetected){document.getElementById(\'' . $blockVisitId . '\').remove()}';
        }

        $inlineJs .= '}';

        // Add CSS
        $this->grav['assets']->addCss('plugin://detect-adblock/assets/css/detect-adblock.css');

      }

      // Block Content operation
      // TODO: Activate by parameter
      $inlineJs .= 'var dabContentBegin = document.getElementById("dab-content-begin");';
      $inlineJs .= 'if(abDetected && dabContentBegin) {';
      $inlineJs .= 'dabContentBegin.style.display=\'block\';';
      $inlineJs .= 'dabDeleteDomElement(dabContentBegin.nextElementSibling, \'dab-content-end\', false);';
      $inlineJs .= '}';

      // Add common JS
      $this->grav['assets']->addJs('plugin://detect-adblock/assets/js/detect-adblock.js');
    }

    $this->grav['assets']->addInlineJs($inlineJs, null, 'bottom');
  }

  /**
   * Add current directory to twig lookup paths.
   */
  public function onTwigTemplatePaths()
  {
    $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
  }

  /**
   * Add content after page content was read into the system.
   */
  public function onPageContentRaw()
  {
    $message_raw = $this->config->get('plugins.detect-adblock.message.content');

    //Extract message according current language
    $message_array_raw = preg_split("/(.*)---([a-zA-Z]{2,3})---(.*)/i", $message_raw, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

    $key='all';
    $message_array = array();
    foreach($message_array_raw as $value){
      $nbChar = strlen($value);
      if (($nbChar > 1) && ($nbChar <=3 )){  // If number of chars > 1 and <= 3, considered as language key
        $key = $value;
      } elseif($nbChar > 3) {               // If number of chars > 3, considered as message content
        $message_array[$key] = trim($value," \t\n\r\0\x0B");
      }
    }

    $lang = $this->grav['language']->getLanguage();
    $message = 'Bad configuration of Message to Display in Plugin parameters.';
    if(isset($message_array[$lang])) {
      $message = $message_array[$lang];
    } elseif(isset($message_array['all'])) {
      $message = $message_array['all'];
    }

    $this->grav['twig']->twig_vars['adblock_message_content'] = $message;

  }

  /**
   * Manage --dab-- tags
   */
  public function onPageContentProcessed(){

    // Get content of template
    $pageContent = $this->grav['twig']->processTemplate('partials/detect-adblock-page-content.html.twig');

    // TODO: To activate with parameters
    $content = $this->grav['page']->getRawContent();
    $content = preg_replace("#<([a-z]{1,5})>---dab---</([a-z]{1,5})>#i",'<div id="dab-content-begin">'.$pageContent.'</div>', $content);
    $content = preg_replace("#<([a-z]{1,5})>---/dab---</([a-z]{1,5})>#i",'<div id="dab-content-end"></div>', $content);
    $this->grav['page']->setRawContent($content);
  }

  /**
   * Clean URL
   * @param $url
   * @return bool|string
   */
  private function cleanUrl($url)
  {
    if (substr($url, 0, 1) == "/") {
      return substr($url, 1, strlen($url) - 1);
    }
    return $url;
  }
}