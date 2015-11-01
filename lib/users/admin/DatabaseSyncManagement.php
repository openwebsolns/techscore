<?php
use \users\admin\AbstractAdminUserPane;
use \users\PaneException;
use \xml5\PageWhiz;

/**
 * Trigger and view database sync reports.
 *
 * Syncs with the database are non-trivial and should be allowed to
 * only some users, provided that they do not overload the system with
 * requests.
 *
 * @author Dayan Paez
 * @created 2014-05-31
 */
class DatabaseSyncManagement extends AbstractAdminUserPane {

  const TIMEOUT_SECONDS = 300;
  const NUM_PER_PAGE = 20;

  private $sailors_url;
  private $schools_url;

  public function __construct(Account $user) {
    parent::__construct("Database sync", $user);

    $this->sailors_url = DB::g(STN::SAILOR_API_URL);
    $this->schools_url = DB::g(STN::SCHOOL_API_URL);

    if ($this->sailors_url === null && $this->schools_url === null)
      throw new PaneException("No database syncing available.");
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific log?
    // ------------------------------------------------------------
    if (isset($args['log'])) {
      try {
        $log = DB::$V->reqID($args, 'log', DB::T(DB::SYNC_LOG), "Invalid log requested.");
        $this->PAGE->addContent(new XP(array(), new XA($this->link(), "← Go back")));
        $this->PAGE->addContent($p = new XPort("Database Sync Details"));
        $p->add(new FItem("Type:", new XStrong(ucwords(implode(", ", $log->updated)))));
        $p->add(new FItem("Started:", new XStrong(DB::howLongFrom($log->started_at))));

        $schools = $log->getSchools();
        $message = new XEm("No new schools.");
        if (count($schools) > 0) {
          $message = new XUl(array('class'=>'inline-list'));
          foreach ($schools as $school)
            $message->add(new XLi(array($school, " ", new XSpan("(" . $school->conference . ")"))));
        }
        $p->add(new FItem("New Schools:", $message));

        $sailors = $log->getSailors();
        $message = new XEm("No new sailors.");
        if (count($sailors) > 0) {
          $message = new XUl(array('class'=>'inline-list'));
          foreach ($sailors as $sailor)
            $message->add(new XLi(array($sailor, " ", new XSpan("(" . $sailor->school . ")"))));
        }
        $p->add(new FItem("New Sailors:", $message));

        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    $past_syncs = DB::getAll(DB::T(DB::SYNC_LOG));

    // ------------------------------------------------------------
    // Trigger a database sync
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Sync Databases"));

    // Timeout?
    $can_submit = true;
    if (count($past_syncs) > 0) {
      $interval = new DateInterval('P0DT' . self::TIMEOUT_SECONDS . 'S');
      $cutoff = new DateTime();
      $cutoff->sub($interval);
      $last = $past_syncs[0];

      if ($last->started_at > $cutoff) {
        $until = clone($last->started_at);
        $until->add($interval);
        $p->add(new XWarning(
                       array("The databases were last synced ", DB::howLongFrom($last->started_at), ". Please try again ", DB::howLongFrom($until), ".")));
        $can_submit = false;
      }
    }

    if ($can_submit) {
      $p->add($form = $this->createForm());
      $form->add(new XP(array(), "Select which part(s) of the database to sync from the list below."));
      $prefix = "Sync:";
      if ($this->sailors_url !== null) {
        $form->add(new FItem($prefix, new FCheckbox(Sync_Log::SAILORS, 1, "Sailors", true)));
        $prefix = "";
      }
      if ($this->schools_url !== null) {
        $form->add(new FItem($prefix, new FCheckbox(Sync_Log::SCHOOLS, 1, "Schools", true)));
      }

      $form->add(new XSubmitP('sync', "Sync now"));
    }

    // ------------------------------------------------------------
    // Auto-merging sailors
    // ------------------------------------------------------------
    if (DB::g(STN::AUTO_MERGE_SAILORS) !== null) {
      $this->PAGE->addContent($p = new XPort("Auto-merging Unregistered Sailors"));
      $p->add($f = $this->createForm());
      $f->add(new FItem("Use year:", new FCheckbox(STN::AUTO_MERGE_YEAR, 1, "Match sailors' class years when merging.", DB::g(STN::AUTO_MERGE_YEAR) !== null)));
      $f->add(new FItem("Use gender:", new FCheckbox(STN::AUTO_MERGE_GENDER, 1, "Match sailors' reported genders when merging.", DB::g(STN::AUTO_MERGE_GENDER) !== null)));
      $f->add($xp = new XSubmitP('set-auto-merge', "Save changes"));
      $xp->add(new XSubmitInput('set-auto-merge-and-run', "Save and Run", array('class'=>'secondary')));
      $xp->add(new XMessage("Sailors are auto-merged on a daily basis."));
    }

    // ------------------------------------------------------------
    // Past syncs
    // ------------------------------------------------------------
    if (count($past_syncs) > 0) {
      $this->PAGE->addContent($p = new XPort("Previous syncs"));
      $p->set('id', 'list');

      $whiz = new PageWhiz(count($past_syncs), self::NUM_PER_PAGE, $this->link(), $args);
      $p->add($whiz->getPageLinks('#list'));
      $past_syncs = $whiz->getSlice($past_syncs);

      $p->add($tab = new XQuickTable(array('class'=>'full sync-log'),
                                     array("Type", "Run time", "Messages", "New Schools", "New Sailors")));
      foreach ($past_syncs as $i => $log) {

        $errors = new XEm("No messages");
        if ($log->error !== null && count($log->error) > 0) {
          $errors = new XUl(array('class' => 'sync-log-errors'));
          foreach ($log->error as $j => $error) {
            $errors->add(new XLi($error));
            if ($j >= 5) {
              $errors->add(new XLi(new XEm(count($log->error) - $j . " more")));
              break;
            }
          }
        }

        $num_schools = count($log->getSchools());
        if ($num_schools > 0)
          $num_schools = new XTD(array(),
                                 array($num_schools, " ",
                                       new XA($this->link(array('log' => $log->id)), "[View]")));
        $num_sailors = count($log->getSailors());
        if ($num_sailors > 0)
          $num_sailors = new XTD(array(),
                                 array($num_sailors, " ",
                                       new XA($this->link(array('log' => $log->id)), "[View]")));

        $tab->addRow(array(
                       ucwords(implode(", ", $log->updated)),
                       DB::howLongFrom($log->started_at),
                       $errors,
                       $num_schools,
                       $num_sailors,
                     ),
                     array('class' => 'row' . ($i % 2)));
      }
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Run sync
    // ------------------------------------------------------------
    if (isset($args['sync'])) {
      $sailors = ($this->sailors_url !== null && DB::$V->incInt($args, Sync_Log::SAILORS, 1, 2) == 1);
      $schools = ($this->schools_url !== null && DB::$V->incInt($args, Sync_Log::SCHOOLS, 1, 2) == 1);

      if (!$sailors && !$schools)
        throw new SoterException("Nothing to sync.");
      
      require_once('scripts/SyncDB.php');
      $syncer = new SyncDB();
      $log = $syncer->run($schools, $sailors);
      
      Session::info(
        sprintf(
          "Database synced: %d schools and %d sailors added.",
          count($log->getSchools()),
          count($log->getSailors())
        )
      );
    }

    // ------------------------------------------------------------
    // Auto-merging settings
    // ------------------------------------------------------------
    if (isset($args['set-auto-merge']) || isset($args['set-auto-merge-and-run'])) {
      if (DB::g(STN::AUTO_MERGE_SAILORS) === null)
        throw new SoterException("Auto-merging is not allowed.");

      DB::s(STN::AUTO_MERGE_YEAR, DB::$V->incInt($args, STN::AUTO_MERGE_YEAR, 1, 2, null));
      DB::s(STN::AUTO_MERGE_GENDER, DB::$V->incInt($args, STN::AUTO_MERGE_GENDER, 1, 2, null));

      Session::pa(new PA("Settings changed."));
      if (isset($args['set-auto-merge-and-run'])) {
        require_once('scripts/MergeUnregisteredSailors.php');

        $merger = new MergeUnregisteredSailors();
        $log = $merger->run(DB::getAll(DB::T(DB::SCHOOL)));

        Session::pa(new PA(sprintf("Auto-merge run: %d sailors throughout %d regattas.",
                                   count($log->getMergeSailorLogs()),
                                   count($log->getMergedRegattas()))));
      }
    }
  }
}
?>