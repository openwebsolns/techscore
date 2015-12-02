<?php
namespace users\membership;

use \users\AbstractUserPane;

use \Account;
use \DB;
use \Conference;
use \Permission;
use \Session;
use \SoterException;
use \STN;
use \UpdateConferenceRequest;
use \UpdateManager;

use \FCheckbox;
use \FItem;
use \FReqItem;
use \XA;
use \XCollapsiblePort;
use \XEm;
use \XForm;
use \XHiddenInput;
use \XP;
use \XPort;
use \XQuickTable;
use \XStrong;
use \XSubmitP;
use \XTextArea;
use \XTextInput;

/**
 * Manage the conferences, or whatever they're called.
 *
 * @author Dayan Paez
 * @version 2015-10-30
 */
class ConferencePane extends AbstractUserPane {

  const SUBMIT_DELETE = 'delete-conferences';
  const SUBMIT_ADD = 'add-conference';
  const SUBMIT_EDIT = 'edit-conference';
  const EDIT_KEY = 'r';

  const ID_REGEX = '^[A-Za-z]+$';

  public function __construct(Account $user) {
    parent::__construct(
      sprintf("%s list", DB::g(STN::CONFERENCE_TITLE)),
      $user
    );
  }

  public function fillHTML(Array $args) {
    if ($this->USER->can(Permission::EDIT_CONFERENCE_LIST)) {
      if (array_key_exists(self::EDIT_KEY, $args)) {
        $conference = DB::getConference($args[self::EDIT_KEY]);
        if ($conference !== null) {
          $this->fillConference($conference, $args);
          return;
        }
        else {
          Session::error("Invalid ID provided.");
        }
      }

      $this->fillConference(new Conference(), $args);
    }

    $this->fillList($args);
  }

  private function fillConference(Conference $conference, Array $args) {
    $newConference = ($conference->id === null);
    $this->PAGE->addContent(new XP(array(), array(new XA($this->link(), "← Back to list"))));

    if ($newConference) {
      $this->PAGE->addContent($p = new XCollapsiblePort("Add new"));
    }
    else {
      $this->PAGE->addContent($p = new XPort("Edit " . $conference));
    }

    $p->add($form = $this->createForm());
    $form->add(new FReqItem("ID:", new XTextInput('id', $conference->id, array('maxlength' => 8, 'pattern' => self::ID_REGEX)), "Must be of the form \"ABC\", and unique. This is the main display identifier."));
    $form->add(new FReqItem("Name:", new XTextInput('name', $conference->name, array('maxlength' => 60)), "Full name of the conference."));
    if (DB::g(STN::SEND_MAIL) !== null) {
      $mail_lists = "";
      if ($conference->mail_lists != null) {
        $mail_lists = implode("\n", $conference->mail_lists);
      }
      $form->add(new FItem("Mailing lists:", new XTextArea('mail_lists', $mail_lists, array('placeholder' => 'Space-delimited list of e-mail addresses.')), "E-mail addresses to be notified of daily summaries."));
    }

    $submitAction = self::SUBMIT_ADD;
    $submitLabel = "Add";
    if (!$newConference) {
      $form->add(new XHiddenInput('original-id', $conference->id));
      $submitAction = self::SUBMIT_EDIT;
      $submitLabel = "Edit";
    }
    $form->add(new XSubmitP($submitAction, $submitLabel));
  }

  private function fillList(Array $args) {
    $this->PAGE->addContent($p = new XPort(DB::g(STN::CONFERENCE_TITLE) . " list"));
    $isPublished = (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) !== null);
    $isMailEnabled = (DB::g(STN::SEND_MAIL) !== null);
    $canEditLists = $this->USER->can(Permission::EDIT_MAILING_LISTS);
    $canEdit = $this->USER->can(Permission::EDIT_CONFERENCE_LIST);

    if ($canEdit) {
      $p->add(new XP(array(), "Click the ID below to edit."));
    }

    $headers = array("Id", "Name");
    if ($isPublished) {
      $headers[] = "Url";
    }
    if ($isMailEnabled) {
      $headers[] = "Mailing lists for daily summaries";
    }
    $headers[] = "# Schools";
    if ($canEdit) {
      $headers[] = "Delete?";
    }
    $table = new XQuickTable(array('class'=>'conference-table'), $headers);
    $p->add($form = $this->createForm());
    $form->add($table);

    $canDelete = false;
    foreach (DB::getConferences() as $i => $conference) {
      $row = array();

      $id = $conference->id;
      if ($canEdit) {
        $id = new XA($this->link(array(self::EDIT_KEY => $id)), $id);
      }
      $row[] = $id;

      $row[] = $conference->name;

      if ($isPublished) {
        $row[] = $conference->getURL();
      }

      if ($isMailEnabled) {
        $link = $this->linkTo('MailingListManagement', array(), 'form-' . DB::g(STN::CONFERENCE_URL));

        $lists = "";
        if ($conference->mail_lists != null && count($conference->mail_lists) > 0) {
          $lists = implode(", ", $conference->mail_lists);
        }
        $row[] = $lists;
      }

      $numSchools = count($conference->getSchools(false));
      $row[] = $numSchools;

      if ($canEdit) {
        $delete = "";
        if ($numSchools == 0) {
          $delete = new FCheckbox('delete[]', $conference->id);
          $canDelete = true;
        }
        $row[] = $delete;
      }

      $table->addRow($row, array('class' => 'row' . $i % 2));
    }

    if ($canDelete) {
      $form->add(new XSubmitP(self::SUBMIT_DELETE, "Delete", array(), true));
    }
  }

  public function process(Array $args) {
    if (array_key_exists(self::SUBMIT_ADD, $args)) {
      $this->processConference($args, false);
    }

    if (array_key_exists(self::SUBMIT_EDIT, $args)) {
      $this->processConference($args, true);
      $this->redirect($this->pane_url());
    }

    if (array_key_exists(self::SUBMIT_DELETE, $args)) {
      $this->processDelete($args);
    }
  }

  /**
   * Adds or edits an existing conference.
   *
   * @param boolean $editing true to expect an existing one.
   */
  private function processConference(Array $args, $editing = false) {
    $conference = new Conference();
    $originalId = null;
    $originalUrl = null;
    if ($editing !== false) {
      $conference = DB::$V->reqID($args, 'original-id', DB::T(DB::CONFERENCE), "Invalid ID to edit.");
      $originalId = $conference->id;
      $originalUrl = $conference->url;
    }

    $matches = DB::$V->reqRE($args, 'id', sprintf('/%s/', self::ID_REGEX), "Invalid or missing ID.");
    $id = $matches[0];
    $existing = DB::getConference($id);
    if ($existing !== null && $existing->id != $originalId) {
      throw new SoterException(
        sprintf(
          "A %s already exists with the ID %s.",
          DB::g(STN::CONFERENCE_TITLE),
          $id
        )
      );
    }

    $name = DB::$V->reqString($args, 'name', 1, 61, "Invalid name provided.");

    $listsString = DB::$V->incString($args, 'mail_lists', 1, 10001, null);
    $mail_lists = array();
    if ($listsString != null) {
      $listsString = preg_replace('/\s+/', ' ', $listsString);
      $listsArray = explode(" ", $listsString);
      for ($i = 0; $i < count($listsArray); $i++) {
        $mail_lists[] = DB::$V->reqEmail($listsArray, $i, sprintf("Invalid e-mail #%d provided.", ($i + 1)));
      }
    }

    $conference->name = $name;
    $conference->mail_lists = $mail_lists;
    $conference->url = $conference->createUrl($id);

    if ($editing !== false) {
      DB::reID($conference, $id);
      Session::info(
        sprintf(
          "%s \"%s\" successfully updated.",
          DB::g(STN::CONFERENCE_TITLE),
          $conference->id
        )
      );
      if ($originalUrl != null) {
        UpdateManager::queueConference(
          $conference,
          UpdateConferenceRequest::ACTIVITY_URL,
          null,
          $originalUrl
        );
      }
    }
    else {
      $conference->id = $id;
      DB::set($conference);
      Session::info(
        sprintf(
          "%s \"%s\" successfully created.",
          DB::g(STN::CONFERENCE_TITLE),
          $conference->id
        )
      );
    }

    UpdateManager::queueConference(
      $conference,
      UpdateConferenceRequest::ACTIVITY_URL,
      null,
      $conference->url
    );
  }

  /**
   * Deletes one or more conferences.
   */
  private function processDelete(Array $args) {
    $requested = DB::$V->reqList($args, 'delete', null, "No items to delete.");
    $toDelete = array();
    foreach ($requested as $id) {
      $conference = DB::getConference($id);
      if ($conference === null) {
        throw new SoterException(
          sprintf(
            "Invalid %s ID provided to delete: %s.",
            DB::g(STN::CONFERENCE_TITLE),
            $id
          )
        );
      }
      if (count($conference->getSchools(false)) > 0) {
        throw new SoterException(
          sprintf(
            "%s %s cannot be deleted because it it associated with one or more schools.",
            DB::g(STN::CONFERENCE_TITLE),
            $conference
          )
        );
      }
      $toDelete[] = $conference;
    }

    foreach ($toDelete as $conference) {
      DB::remove($conference);

      if ($conference->url !== null) {
        UpdateManager::queueConference(
          new Conference(),
          UpdateConferenceRequest::ACTIVITY_URL,
          null,
          $conference->url
        );
      }
    }
    Session::info(sprintf("Removed %d item(s).", count($toDelete)));
  }
}