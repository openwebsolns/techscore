<?php
namespace utils;
use \Permission;

/*
 * The structure for the non-scoring panes.
 *
 * @author Dayan Paez
 * @created 2015-03-29
 */
return array(
  'AddTeamsPane' => array(
    RouteManager::NAME => "Add team",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/teams'),
    RouteManager::PERMISSIONS => array()
  ),

  'DeleteRegattaPane' => array(
    RouteManager::NAME => "Delete",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/delete'),
    RouteManager::PERMISSIONS => array()
  ),

  'DeleteTeamsPane' => array(
    RouteManager::NAME => "Remove team",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/remove-teams'),
    RouteManager::PERMISSIONS => array()
  ),

  'DetailsPane' => array(
    RouteManager::NAME => "Settings",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/settings'),
    RouteManager::PERMISSIONS => array()
  ),

  'DropPenaltyPane' => array(
    RouteManager::NAME => "Drop penalty",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/drop-penalty'),
    RouteManager::PERMISSIONS => array()
  ),

  'EditTeamsPane' => array(
    RouteManager::NAME => "Edit names",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/edit-teams'),
    RouteManager::PERMISSIONS => array()
  ),

  'EnterFinishPane' => array(
    RouteManager::NAME => "Enter finish",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/finishes'),
    RouteManager::PERMISSIONS => array()
  ),

  'EnterPenaltyPane' => array(
    RouteManager::NAME => "Add penalty",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/penalty'),
    RouteManager::PERMISSIONS => array()
  ),

  'FinalizePane' => array(
    RouteManager::NAME => "Finalize",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/finalize'),
    RouteManager::PERMISSIONS => array()
  ),

  'ManualTweakPane' => array(
    RouteManager::NAME => "Manual setup",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/manual-rotation'),
    RouteManager::PERMISSIONS => array()
  ),

  'NotesPane' => array(
    RouteManager::NAME => "Race notes",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/notes'),
    RouteManager::PERMISSIONS => array()
  ),

  'NoticeBoardPane' => array(
    RouteManager::NAME => "Notice Board",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/notices'),
    RouteManager::PERMISSIONS => array()
  ),

  'RacesPane' => array(
    RouteManager::NAME => "Add/edit races",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/races'),
    RouteManager::PERMISSIONS => array()
  ),

  'RankTeamsPane' => array(
    RouteManager::NAME => "Rank teams",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/rank'),
    RouteManager::PERMISSIONS => array()
  ),

  'ReplaceTeamPane' => array(
    RouteManager::NAME => "Sub team",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/substitute'),
    RouteManager::PERMISSIONS => array()
  ),

  'RpEnterPane' => array(
    RouteManager::NAME => "Enter RP",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/rp'),
    RouteManager::PERMISSIONS => array()
  ),

  'RpMissingPane' => array(
    RouteManager::NAME => "Missing RP",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/missing'),
    RouteManager::PERMISSIONS => array()
  ),

  'SailsPane' => array(
    RouteManager::NAME => "Set rotation",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/rotations'),
    RouteManager::PERMISSIONS => array()
  ),

  'ScorersPane' => array(
    RouteManager::NAME => "Scorers",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/scorers'),
    RouteManager::PERMISSIONS => array()
  ),

  'SummaryPane' => array(
    RouteManager::NAME => "Summaries",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/summaries'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamEditRoundPane' => array(
    RouteManager::NAME => "Edit round",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/rounds'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamEnterFinishPane' => array(
    RouteManager::NAME => "Enter finish",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/finishes'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamEnterPenaltyPane' => array(
    RouteManager::NAME => "Add penalty",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/penalty'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamOrderRoundsPane' => array(
    RouteManager::NAME => "Order rounds",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/order-rounds'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamPartialRankPane' => array(
    RouteManager::NAME => "Partial ranking",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/partial'),
    RouteManager::PERMISSIONS => array()
  ),

  'DivisionPenaltyPane' => array(
    RouteManager::NAME => "Team penalty",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/team-penalty'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamRacesPane' => array(
    RouteManager::NAME => "Add round",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/races'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamRankGroupPane' => array(
    RouteManager::NAME => "Rank groups",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/group'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamReplaceTeamPane' => array(
    RouteManager::NAME => "Sub team",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/substitute'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamRpEnterPane' => array(
    RouteManager::NAME => "Enter RP",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/rp'),
    RouteManager::PERMISSIONS => array()
  ),

  'TweakSailsPane' => array(
    RouteManager::NAME => "Tweak sails",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/tweak-sails'),
    RouteManager::PERMISSIONS => array()
  ),

  'UnregisteredSailorPane' => array(
    RouteManager::NAME => "Unregistered",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/score/%s/unregistered'),
    RouteManager::PERMISSIONS => array()
  ),

  'BoatsDialog' => array(
    RouteManager::NAME => "Boat rankings",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/boats'),
    RouteManager::PERMISSIONS => array()
  ),

  'RegistrationsDialog' => array(
    RouteManager::NAME => "View registrations",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/sailors'),
    RouteManager::PERMISSIONS => array()
  ),

  'RotationDialog' => array(
    RouteManager::NAME => "View rotations",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/rotation'),
    RouteManager::PERMISSIONS => array()
  ),

  'RpDownloadDialog' => array(
    RouteManager::NAME => "Download filled RP",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/download/%s/rp'),
    RouteManager::PERMISSIONS => array()
  ),

  'RpTemplateDownload' => array(
    RouteManager::NAME => "RP Template",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/download/%s/rp-template'),
    RouteManager::PERMISSIONS => array()
  ),

  'ScoresChartDialog' => array(
    RouteManager::NAME => "Rank chart",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/chart'),
    RouteManager::PERMISSIONS => array()
  ),

  'ScoresCombinedDialog' => array(
    RouteManager::NAME => "View combined scores",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/combined'),
    RouteManager::PERMISSIONS => array()
  ),

  'ScoresDivisionalDialog' => array(
    RouteManager::NAME => "View division rank",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/ranking'),
    RouteManager::PERMISSIONS => array()
  ),

  'ScoresDivisionDialog' => array(
    RouteManager::NAME => "View division scores",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/scores/A'),
    RouteManager::PERMISSIONS => array()
  ),

  'ScoresFullDialog' => array(
    RouteManager::NAME => "View scores",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/scores'),
    RouteManager::PERMISSIONS => array()
  ),

  'ScoresGridDialog' => array(
    RouteManager::NAME => "View scores",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/scores'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamRacesDialog' => array(
    RouteManager::NAME => "View races",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/races'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamRankingDialog' => array(
    RouteManager::NAME => "View rankings",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/ranking'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamRegistrationsDialog' => array(
    RouteManager::NAME => "View registrations",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/sailors'),
    RouteManager::PERMISSIONS => array()
  ),

  'TeamRotationDialog' => array(
    RouteManager::NAME => "View rotations",
    RouteManager::PATH => 'tscore',
    RouteManager::URLS => array('/view/%s/rotation'),
    RouteManager::PERMISSIONS => array()
  ),

);