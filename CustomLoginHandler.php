<?php

final class CustomLoginHandler
  extends PhabricatorAuthLoginHandler {

  public function getAuthLoginHeaderContent() {
    return phutil_safe_html(
  '<div style="font-weight:bold;font-size:1.8em;text-align:center">Log in or register to Miraheze Phabricator</div><p style="font-size:1.1em;text-align:center;line-height:1.5;padding:10px">Click the MediaWiki button below to connect with your Miraheze wiki account.<br>If you encounter any issues please <a href="https://meta.miraheze.org/wiki/Help_center">contact us</a>.</p>');  }

}
