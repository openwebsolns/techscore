<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * View logged-in users, and kick them out
 *
 * @author Dayan Paez
 * @created 2013-10-30
 */
class LoggedInUsers extends AbstractAdminUserPane {

  const NUM_PER_PAGE = 20;

  public function __construct(Account $user) {
    parent::__construct("Logged-in Users", $user);
  }

  protected function fillHTML(Array $args) {
    require_once('TSSessionHandler.php');
    $this->PAGE->addContent($p = new XPort("Logged in users"));

    $sessions = TSSessionHandler::getActive();
    $num_sessions = count($sessions);
    if ($num_sessions == 0) {
      $p->add(new XP(array('class'=>'warning'), "There are no active sessions. Which is troubling, considering you seem to be logged-in."));
      return;
    }

    // Pagination
    $num_pages = intval($num_sessions / self::NUM_PER_PAGE) + 1;
    $pageset  = DB::$V->incInt($args, 'r', 1, $num_pages + 1, 1);
    $startint = self::NUM_PER_PAGE * ($pageset - 1);

    $p->add(new XP(array(),
                   array("Logging-off a user will invalidate that user's current session immediately. The next time that user refreshes the page or visits or clicks a link, they will be prompted to re-login. ",
                         new XStrong("This may result in data loss for the user!"))));

    if ($num_pages > 1) {
      $p->add(new XP(array('class'=>'warning'), sprintf("There are %d logged-in users.", $num_sessions)));
      require_once('xml5/PageWhiz.php');
      $whiz = new PageWhiz($num_sessions, self::NUM_PER_PAGE, $this->link(), $_GET);
      $p->add($whiz->getPages());
    }
                         
    $p->add($tab = new XQuickTable(array('class'=>'sessions-table'),
                                   array("Account", "Type", "Expires", "Last modified", "")));
    for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < count($sessions); $i++) {
      $session = $sessions[$i];
      $user = $this->extractUser($session);
      $type = "Session";
      $exp = $session->expires;
      if ($exp === null) {
        $exp = clone($session->last_modified);
        $exp->add(new DateInterval(sprintf('P0DT%dS', TSSessionHandler::IDLE_TIME)));
      }
      else
        $type = "Long-lived";

      $f = "";
      $class = 'row' . ($i % 2);
      if ($session->id == session_id())
        $class .= ' current-session';
      elseif ($user !== null) {
        $f = $this->createForm();
        $f->add(new XHiddenInput('websession', $session->id));
        $f->add(new XSubmitDelete('delete', "Log-off user"));
      }
      else {
        $user = new XEm("Not logged-in");
      }

      $tab->addRow(array($user,
                         $type,
                         DB::howLongFrom($exp),
                         DB::howLongFrom($session->last_modified), // time ago?
                         $f),
                   array('class'=>$class));
    }
  }

  private function extractUser(Websession $s) {
    $old = $_SESSION;
    session_decode($s->sessiondata);
    $ret = null;
    if (isset($_SESSION['data'])) {
      $val = unserialize($_SESSION['data']);
      if (isset($val['user']))
        $ret = DB::getAccount($val['user']);
    }
    $_SESSION = $old;
    return $ret;
  }

  public function process(Array $args) {
    if (isset($args['delete'])) {
      $sess = DB::$V->reqID($args, 'websession', DB::T(DB::WEBSESSION), "Invalid session provided.");
      if ($sess->id == session_id())
        throw new SoterException("To kick yourself out, use the \"Logout\" button.");
      $user = $this->extractUser($sess);
      if ($user === null)
        throw new SoterException("No user has logged-in for chosen session, thus it will not be removed.");
      DB::remove($sess);
      Session::pa(new PA(sprintf("Deleted a user session for %s.", $user)));
    }
  }
}
?>