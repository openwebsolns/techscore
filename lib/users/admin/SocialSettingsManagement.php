<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the regatta types and their ranks
 *
 * @author Dayan Paez
 * @created 2013-03-06
 */
class SocialSettingsManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Social settings", $user);
    $this->page_url = 'social';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent(new XP(array(), "Use the form below to set the account names and other pertinent information for use on the public site. If provided, the information will be used on all pages created from this point on."));

    $this->PAGE->addContent($p = new XPort("Google Custom Search"));
    $p->add($f = $this->createForm());
    $f->add(new FItem("Google GCSE ID:", new XTextInput(Setting::GCSE_ID, DB::getSetting(Setting::GCSE_ID)), "As provided in the embed code"));
    $f->add(new XSubmitP('set-settings', "Save changes"));

    $this->PAGE->addContent($p = new XPort("Facebook settings"));
    $p->add($f = $this->createForm());
    $f->add(new FItem("Facebook username:", new XTextInput(Setting::FACEBOOK, DB::getSetting(Setting::FACEBOOK)), "Create link to Facebook account."));
    $f->add(new FItem("Facebook App ID:", new XTextInput(Setting::FACEBOOK_APP_ID, DB::getSetting(Setting::FACEBOOK_APP_ID)), "Needed to create \"Like\" button."));
    $f->add(new XSubmitP('set-settings', "Save changes"));

    $this->PAGE->addContent($p = new XPort("Twitter settings"));
    $p->add($f = $this->createForm());
    $f->add(new FItem("Username:", new XTextInput(Setting::TWITTER, DB::getSetting(Setting::TWITTER)), "Create link to Twitter account and \"Tweet\" button."));
    $f->add(new XP(array(), "The following parameters are all needed for the automatic tweeting functionality. They can be obtained from the Twitter Developer account, under \"My applications\"."));
    $f->add(new FItem("Consumer Key:", new XTextInput(Setting::TWITTER_CONSUMER_KEY, DB::getSetting(Setting::TWITTER_CONSUMER_KEY))));
    $f->add(new FItem("Consumer Secret:", new XPasswordInput(Setting::TWITTER_CONSUMER_SECRET, DB::getSetting(Setting::TWITTER_CONSUMER_SECRET))));
    $f->add(new FItem("Application Token:", new XTextInput(Setting::TWITTER_OAUTH_TOKEN, DB::getSetting(Setting::TWITTER_OAUTH_TOKEN))));
    $f->add(new FItem("Application Secret:", new XPasswordInput(Setting::TWITTER_OAUTH_SECRET, DB::getSetting(Setting::TWITTER_OAUTH_SECRET))));
    $f->add(new XSubmitP('set-settings', "Save changes"));

    $this->PAGE->addContent($p = new XPort("UserVoice Feedback settings"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(), "All of these parameters are needed in order to integrat the \"Feedback\" badge into the public sites."));
    $f->add(new FItem("Uservoice ID:", new XTextInput(Setting::USERVOICE_ID, DB::getSetting(Setting::USERVOICE_ID)), "22 alphanumeric characters"));
    $f->add(new FItem("Form ID:", new XTextInput(Setting::USERVOICE_FORUM, DB::getSetting(Setting::USERVOICE_FORUM)), "Formum ID obtained from embed code"));
    $f->add(new XSubmitP('set-settings', "Save changes"));

    $this->PAGE->addContent($p = new XPort("Flickr settings"));
    $p->add($f = $this->createForm());
    $f->add(new FItem("Flickr username:", new XTextInput(Setting::FLICKR_NAME, DB::getSetting(Setting::FLICKR_NAME)), "Will be used to link to account"));
    $f->add(new FItem("Slideshow ID:", new XTextInput(Setting::FLICKR_ID, DB::getSetting(Setting::FLICKR_ID)), "Embeds slideshow in front page on non-sailing days"));
    $f->add(new XSubmitP('set-settings', "Save changes"));
  }

  public function process(Array $args) {
    if (isset($args['set-settings'])) {
      $names = DB::getSettingNames();
      $upd = 0;
      foreach ($args as $key => $val) {
        if (in_array($key, $names)) {
          $val = DB::$V->incString($args, $key, 1, 16000, null);
          if ($val != DB::getSetting($key)) {
            DB::setSetting($key, $val);
            $upd++;
          }
        }
      }
      if ($upd == 0)
        Session::pa(new PA("No settings were updated.", PA::I));
      else
        Session::pa(new PA(sprintf("Updated %d settings.", $upd)));
    }
  }
}
?>