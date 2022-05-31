<?php
/*
 * This file is part of Techscore
 */

/**
 * "Sticky" key-value pairs for the application, as handled by DB
 *
 * @author Dayan Paez
 * @version 2013-09-16
 */
class STN extends DBObject {
  const APP_NAME = 'app_name';
  const APP_VERSION = 'app_version';
  const APP_COPYRIGHT = 'app_copyright';
  const TS_FROM_MAIL = 'ts_from_mail';
  const SAILOR_API_URL = 'sailor_api_url';
  const SCHOOL_API_URL = 'school_api_url';
  const HELP_HOME = 'help_home';
  const DIVERT_MAIL = 'divert_mail';
  const SCORING_OPTIONS = 'scoring_options';
  const CONFERENCE_TITLE = 'conference_title';
  const CONFERENCE_SHORT = 'conference_short';
  const CONFERENCE_URL = 'conference_url';
  const ALLOW_CROSS_RP = 'allow_cross_rp';
  const PDFLATEX_SOCKET = 'pdflatex_socket';
  const LONG_SESSION_LIMIT = 'long_session_limit';
  const NOTICE_BOARD_SIZE = 'notice_board_size';
  const REGISTRATION_TIMEOUT = 'registration_timeout';

  const RP_SINGLEHANDED = 'rp-singlehanded';
  const RP_1_DIVISION = 'rp-1-division';
  const RP_2_DIVISION = 'rp-2-division';
  const RP_3_DIVISION = 'rp-3-division';
  const RP_4_DIVISION = 'rp-4-division';
  const RP_TEAM_RACE = 'rp-team-race';

  const TWITTER_URL_LENGTH = 'twitter_url_length';
  const SEND_MAIL = 'send_mail';
  const ALLOW_REGISTER = 'allow_register';
  const ORG_NAME = 'org_name';
  const ORG_URL = 'org_url';
  const ORG_TEAMS_URL = 'org_teams_url';

  const GCSE_ID = 'gcse_id';
  const GOOGLE_ANALYTICS = 'google_analytics';
  const GOOGLE_PLUS = 'google_plus';
  const FACEBOOK = 'facebook';
  const FACEBOOK_APP_ID = 'facebook_app_id';
  const TWITTER = 'twitter';
  const TWITTER_CONSUMER_KEY = 'twitter_consumer_key';
  const TWITTER_CONSUMER_SECRET = 'twitter_consumer_secret';
  const TWITTER_OAUTH_TOKEN = 'twitter_oauth_token';
  const TWITTER_OAUTH_SECRET = 'twitter_oauth_secret';
  const USERVOICE_ID = 'uservoice_id';
  const USERVOICE_FORUM = 'uservoice_forum';
  const FLICKR_NAME = 'flickr_name';
  const FLICKR_ID = 'flickr_id';
  const PAYPAL_HOSTED_BUTTON_ID = 'paypal_hosted_button_id';

  const MAIL_REGISTER_USER = 'mail_register_user';
  const MAIL_REGISTER_STUDENT = 'mail_register_student';
  const MAIL_VERIFY_EMAIL = 'mail_verify_email';
  const MAIL_REGISTER_ADMIN = 'mail_register_admin';
  const MAIL_APPROVED_USER = 'mail_approved_user';
  const MAIL_UNFINALIZED_REMINDER = 'mail_unfinalized_reminder';
  const MAIL_MISSING_RP_REMINDER = 'mail_missing_rp_reminder';
  const MAIL_UPCOMING_REMINDER = 'mail_upcoming_reminder';
  /**
   * Sent at the end of each day of competition
   */
  const MAIL_RP_REMINDER = 'mail_rp_reminder';
  /**
   * If available, send e-mails in AutoFinalize script.
   */
  const MAIL_AUTO_FINALIZE_PENALIZED = 'mail_auto_finalize_penalized';

  /**
   * Include missing RP regattas in unfinalized reminder e-mail
   */
  const INCLUDE_MISSING_RP_IN_UNFINALIZED_REMINDER = 'include_missing_rp_in_unfinalized_reminder';

  const DEFAULT_START_TIME = 'default_start_time';
  const ALLOW_HOST_VENUE = 'allow_host_venue';
  const PUBLISH_CONFERENCE_SUMMARY = 'publish_conference_summary';

  const AUTO_MERGE_SAILORS = 'auto_merge_sailors';
  const AUTO_MERGE_GENDER = 'auto_merge_gender';
  const AUTO_MERGE_YEAR = 'auto_merge_year';

  const REGATTA_SPONSORS = 'regatta_sponsors';

  /**
   * Publish sailor profiles to the public site
   */
  const SAILOR_PROFILES = 'sailor_profiles';

  /**
   * Enforce uniqueness for each sailor by appending each upstream ID
   * with three extra digits (YYS) representing the year (YY) and then
   * the season (S) as either 1=fall, etc.
   *
   * @see SAILOR_API_URL
   */
  const UNIQUE_SEASON_SAILOR = 'unique_season_sailor';

  /**
   * Allow the "reserves" feature when entering RP information.
   */
  const ALLOW_RESERVES = 'allow_reserves';

  /**
   * @var boolean allow the AutoFinalize feature.
   */
  const ALLOW_AUTO_FINALIZE = 'allow_auto_finalize';

  /**
   * @var boolean enable the AutoFinalize functionality.
   */
  const AUTO_FINALIZE_ENABLED = 'auto_finalize_enabled';

  /**
   * Auto-finalize regattas this many days AFTER the last day of the
   * event. If null, the feature is considered turned off.
   */
  const AUTO_FINALIZE_AFTER_N_DAYS = 'auto_finalize_after_n_days';

  /**
   * Automatically assess a Missing RP penalty to teams when auto
   * finalizing regattas (see AUTO_FINALIZE_AFTER_N_DAYS).
   */
  const AUTO_ASSESS_MRP_ON_AUTO_FINALIZE = 'auto_assess_mrp_on_auto_finalize';

  /**
   * Whether to allow non-logged in users to search.
   */
  const EXPOSE_SAILOR_SEARCH = 'expose_sailor_search';

  /**
   * Concerning student registration.
   */
  const ALLOW_SAILOR_REGISTRATION = 'allow_sailor_registration';
  /**
   * Whether to actually use this feature (once allowed).
   */
  const ENABLE_SAILOR_REGISTRATION = 'enable_sailor_registration';

  const LABEL_PARTICIPANT_COED = 'label_participant_coed';
  const LABEL_PARTICIPANT_WOMEN = 'label_participant_women';

  const DIVISION_PENALTY_PFD = 'division_penalty_pfd';
  const DIVISION_PENALTY_LOP = 'division_penalty_lop';
  const DIVISION_PENALTY_MRP = 'division_penalty_mrp';
  const DIVISION_PENALTY_GDQ = 'division_penalty_gdq';

  const RECAPTCHA_SITE_KEY = 'recaptcha_site_key';
  const RECAPTCHA_SECRET_KEY = 'recaptcha_secret_key';

  public $value;
  public function db_name() { return 'setting'; }
  protected function db_cache() { return true; }
  public function __toString() { return $this->value; }

  /**
   * Fetches default value (if none found in database)
   *
   * @param Const $name the setting
   * @return String|null the default value
   */
  public static function getDefault($name) {
    switch ($name) {
    case self::TS_FROM_MAIL:
      return Conf::$ADMIN_MAIL;

    case self::APP_NAME:
      return "Techscore";

    case self::APP_VERSION:
      return "3.3";

    case self::APP_COPYRIGHT:
      return "© OpenWeb Solutions, LLC 2008-2013";

    case self::SCORING_OPTIONS:
      return sprintf("%s\0%s\0%s", Regatta::SCORING_STANDARD, Regatta::SCORING_COMBINED, Regatta::SCORING_TEAM);

    case self::CONFERENCE_TITLE:
      return "Conference";

    case self::CONFERENCE_SHORT:
      return "Conf.";

    case self::CONFERENCE_URL:
      return 'conferences';

    case self::DEFAULT_START_TIME:
      return "10:00";

    case self::LONG_SESSION_LIMIT:
      return 3;

    case self::NOTICE_BOARD_SIZE:
      return 5242880; // 5MB

    case self::REGISTRATION_TIMEOUT:
      return "2 hours";

    case self::MAIL_VERIFY_EMAIL:
      return "Dear {FIRST_NAME},\n\nThis message is part of a request to change e-mail addresses associated with your account. To finish, please paste the token provided below as instructed on the site. If you did not request this message, kindly disregard this message.\n\nToken: {BODY}\n\nThank you";

    case self::AUTO_FINALIZE_AFTER_N_DAYS:
      return 2;

    case self::LABEL_PARTICIPANT_COED:
      return "Coed";

    case self::LABEL_PARTICIPANT_WOMEN:
      return "Women";

    case self::DIVISION_PENALTY_PFD:
      return 20;

    case self::DIVISION_PENALTY_LOP:
      return 20;

    case self::DIVISION_PENALTY_MRP:
      return 20;

    case self::DIVISION_PENALTY_GDQ:
      return 20;

    default:
      return null;
    }
  }
}
