<?php
use \ui\FilterFieldset;
use \users\admin\AbstractAccountPane;
use \utils\AccountSearcher;
use \utils\HttpResponse;
use \xml5\PageWhiz;

/**
 * Pane to edit user accounts
 *
 */
class AccountsPane extends AbstractAccountPane {

  const NUM_PER_PAGE = 20;
  const INPUT_DOWNLOAD = 'download';
  const DOWNLOAD_CSV = 'csv';

  const DF_ID = 'id';
  const DF_EMAIL = 'email';
  const DF_FIRST_NAME = 'first_name';
  const DF_LAST_NAME = 'last_name';
  const DF_SCHOOLS = 'schools';
  const DF_ROLE = 'role';
  const DF_STATUS = 'status';

  const SCHOOLS_ALL = "All (admin)";

  private static $CSV_DISPLAY_FIELDS = array(
    self::DF_ID,
    self::DF_FIRST_NAME,
    self::DF_LAST_NAME,
    self::DF_EMAIL,
    self::DF_SCHOOLS,
    self::DF_ROLE,
    self::DF_STATUS,
  );

  /**
   * Creates a new such pane
   *
   * @param Account $user the administrator with access
   * @throws InvalidArgumentException if the User is not an
   * administrator
   */
  public function __construct(Account $user) {
    parent::__construct("All users", $user);
  }

  /**
   * Override to handle download argument.
   *
   * @param Array $args from request
   * @return response
   */
  public function processGET(Array $args): HttpResponse {
    if (array_key_exists(self::INPUT_DOWNLOAD, $args)) {
      try {
        $searcher = AccountSearcher::fromArgs($args);
        $users = $searcher->doSearch($this->USER);
        return $this->downloadAccounts($users);
      } catch (SoterException $e) {
        return HttpResponse::badRequest($e->getMessage(), ['Content-Type' => 'text/plain']);
      }
    }

    if (DB::$V->incString($_SERVER, 'HTTP_ACCEPT', 1, 1000) === 'application/json') {
      $responseHeaders = ['Content-Type' => 'application/json'];
      try {
        $searcher = AccountSearcher::fromArgs($args);
        $users = $searcher->doSearch($this->USER);
        $response = $this->toJsonResponse($users);
        return HttpResponse::ok(json_encode($response), $responseHeaders);
      } catch (SoterException $e) {
        $response = [ 'error' => $e->getMessage() ];
        return HttpResponse::badRequest(json_encode($response), $responseHeaders);
      }
    }

    return parent::processGET($args);
  }

  /**
   * Generates and returns the HTML page
   *
   */
  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific user
    // ------------------------------------------------------------
    if (isset($args['id'])) {
      $user = DB::getAccount($args['id']);
      if ($user !== null) {
        $this->fillUser($user);
        return;
      }
      Session::pa(new PA("Invalid account requested.", PA::I));
    }

    // ------------------------------------------------------------
    // Current users
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Current users"));
    $p->add(new XP(array(), "Click on the user's name to edit."));

    $users = [];
    $accountSearcher = AccountSearcher::createDefault();
    $error_mes = null;
    try {
      $accountSearcher = AccountSearcher::fromArgs($args);
      $users = $accountSearcher->doSearch($this->USER);
    } catch (SoterException $e) {
      $error_mes = $e->getMessage();
    }

    // Offer pagination
    $qry = DB::$V->incString($args, AccountSearcher::FIELD_QUERY, 0, 256);
    $whiz = new PageWhiz(count($users), self::NUM_PER_PAGE, $this->link(), $args);
    $p->add($whiz->getSearchForm($qry, AccountSearcher::FIELD_QUERY, "No users match your request.", "Search users: "));
    $users = $whiz->getSlice($users);
    $ldiv = $whiz->getPageLinks();

    // Filter
    $ts_role_opts = array("" => "[All]");
    foreach (DB::getAll(DB::T(DB::ROLE)) as $val)
      $ts_role_opts[$val->id] = $val;

    $role_opts = array("" => "[All]");
    foreach (Account::getRoles() as $key => $val)
      $role_opts[$key] = $val;

    $stat_opts = array("" => "[All]");
    foreach (Account::getStatuses() as $key => $val)
      $stat_opts[$key] = $val;

    $p->add($fs = new FilterFieldset());
    $fs->add($f = $this->createForm(XForm::GET));

    foreach ($args as $key => $val) {
      if (!in_array($key, array('role', 'status')))
        $f->add(new XHiddenInput($key, $val));
    }
    $f->add(
      new XP(
        array(),
        array(
          new FormGroup(
            array(
              new XSpan("Role:", array('class'=>'span_h')),
              XSelect::fromArray(
                AccountSearcher::FIELD_TS_ROLE,
                $ts_role_opts,
                ($accountSearcher->ts_role) ? $accountSearcher->ts_role->id : null))
          ),
          new FormGroup(
            array(
              new XSpan("School Role:", array('class'=>'span_h')),
              XSelect::fromArray(
                AccountSearcher::FIELD_ROLE,
                $role_opts,
                $accountSearcher->role))
          ),
          new FormGroup(
            array(
              new XSpan("Status:", array('class'=>'span_h')),
              XSelect::fromArray(
                AccountSearcher::FIELD_STATUS,
                $stat_opts,
                $accountSearcher->status))
          ),
          new XSubmitInput('go', "Apply", array('class'=>'inline')))));

    // Create table, if applicable
    if ($error_mes !== null) {
      $p->add(new XWarning($error_mes));
    } elseif (count($users) > 0) {
      $p->add($ldiv);

      $downloadArgs = $args;
      $downloadArgs[self::INPUT_DOWNLOAD] = self::DOWNLOAD_CSV;
      $ldiv->add(new XSpan(new XA($this->link($downloadArgs), "Download", array('target'=>'_blank')), array('class' => 'download-link')));

      $can_usurp = $this->USER->can(Permission::USURP_USER);
      $headers = array("Name", "Email", "Schools", "Role", "School Role", "Status");
      if ($can_usurp)
        $headers[] = "Usurp";
      $p->add($tab = new XQuickTable(array('class'=>'users-table'), $headers));

      foreach ($users as $i => $user) {
        $fields = $this->toDisplayFields($user);

        $schools = $fields[self::DF_SCHOOLS];
        if ($schools === self::SCHOOLS_ALL) {
          $schools = new XEm($schools);
        }
        $row = array(
          new XA($this->link(array('id'=>$fields[self::DF_ID])), $user),
          $fields[self::DF_EMAIL],
          $schools,
          $fields[self::DF_ROLE],
          ucwords($user->role),
          new XSpan($fields[self::DF_STATUS], array('class'=>'stat user-' . $user->status))
        );

        if ($can_usurp) {
          $form = "";
          if ($user->status == Account::STAT_ACTIVE) {
            $form = $this->createForm();
            $form->add(new XHiddenInput('user', $user->id));
            $form->add(new XSubmitInput('usurp-user', "Usurp"));
          }
          $row[] = $form;
        }
        $tab->addRow($row, array('class'=>'row'.($i % 2)));
      }
      $p->add($ldiv);
    }

    $this->PAGE->addContent($p = new XPort("Legend"));
    $p->add(new XP(array(), "The \"Status\" indicators have the following meaning:"));
    $p->add($tab = new XQuickTable(array('class'=>'users-legend'), array("Status", "Meaning")));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_REQUESTED), array('class'=>'stat user-' . Account::STAT_REQUESTED)),
                       "The account was requested, but user has not yet confirmed the e-mail address is valid by following the link sent."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_PENDING), array('class'=>'stat user-' . Account::STAT_PENDING)),
                       "The user has confirmed ownership of e-mail address and the account needs to be approved or rejected by an administrator."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_ACCEPTED), array('class'=>'stat user-' . Account::STAT_ACCEPTED)),
                       "The account has been approved by an administrator, but the user has not agreed to the EULA."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_ACTIVE), array('class'=>'stat user-' . Account::STAT_ACTIVE)),
                       "The user has accepted the EULA."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_REJECTED), array('class'=>'stat user-' . Account::STAT_REJECTED)),
                       "The account has been rejected by an administrator. The e-mail address will never be used for another account."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_INACTIVE), array('class'=>'stat user-' . Account::STAT_INACTIVE)),
                       "The account has been removed. Functionally similar to a \"rejected\" status."));
  }

  private function toDisplayFields(Account $user) {
    if ($user->isAdmin())
      $schools = self::SCHOOLS_ALL;
    else {
      $schools = "";
      $j = 0;
      foreach ($user->getConferences() as $conf) {
        if ($j > 0)
          $schools .= ", ";
        $schools .= $conf;
        $j++;
      }

      foreach ($user->getSchools(null, false) as $school) {
        if ($j > 0)
          $schools .= ", ";
        $schools .= $school->nick_name;
        $j++;
      }
    }

    return array(
      self::DF_ID => $user->id,
      self::DF_FIRST_NAME => $user->first_name,
      self::DF_LAST_NAME => $user->last_name,
      self::DF_EMAIL => $user->email,
      self::DF_SCHOOLS => $schools,
      self::DF_ROLE => (string) $user->ts_role,
      self::DF_STATUS => ucwords($user->status),
    );
  }

  private function downloadAccounts($users): HttpResponse {
    $headers = [
      'Content-type' => 'application/octet-stream',
      'Content-Disposition' => 'attachment; filename=techscore-accounts.tsv',
    ];

    $csv = sprintf("%s\n", implode("\t", self::$CSV_DISPLAY_FIELDS));
    foreach ($users as $user) {
      $fields = $this->toDisplayFields($user);
      foreach (self::$CSV_DISPLAY_FIELDS as $i => $field) {
        if ($i > 0) {
          $csv .= "\t";
        }
        $csv .= str_replace("\t", " ", $fields[$field]);
      }
      $csv .= "\n";
    }

    return HttpResponse::ok($csv, $headers);
  }

  private function toJsonResponse($users): Array {
    $result = array();
    foreach ($users as $user) {
      $result[] = $this->toDisplayFields($user);
    }

    return $result;
  }
}
