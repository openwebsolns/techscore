<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the different permission roles in the system
 *
 * @author Dayan Paez
 * @created 2014-05-07
 */
class RoleManagementPane extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Roles", $user);
    $this->page_url = 'roles';
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Edit existing?
    // ------------------------------------------------------------
    if (isset($args['id'])) {
      try {
        $role = DB::$V->reqID($args, 'id', DB::$ROLE, "Invalid role requested.");
        $this->PAGE->addContent($p = new XPort("Edit role"));
        $p->add($form = $this->createForm());

        $form->add(new FReqItem("Name:", new XTextInput('title', $role->title, array('max'=>256))));
        $form->add(new FItem("Description:", new XTextArea('description', $role->description, array('placeholder'=>"Helpful descriptors help you stay organized."))));
        $form->add(new FReqItem("Permissions:", $sel = new XSelectM('permissions[]', array('size'=>10))));

        // Fill select
        $existing = array();
        foreach ($role->getPermissions() as $perm)
          $existing[$perm->id] = $perm;

        $groups = array();
        foreach (DB::getAll(DB::$PERMISSION) as $perm) {
          if (!isset($groups[$perm->category])) {
            $sel->add($group = new FOptionGroup($perm->category));
            $groups[$perm->category] = $group;
          }
          $groups[$perm->category]->add($opt = new FOption($perm->id, $perm));
          if (isset($existing[$perm->id]))
            $opt->set('selected', 'selected');
        }

        $form->add($xp = new XSubmitP('edit', "Save Changes"));
        $xp->add(new XHiddenInput('role', $role->id));
        $xp->add(new XA('/roles', "Cancel"));

        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // List current roles
    // ------------------------------------------------------------
    $roles = DB::getAll(DB::$ROLE);
    if (count($roles) > 0) {
      $this->PAGE->addContent($p = new XPort("Existing roles"));
      $p->add(new XP(array(), "Below is a list of existing roles and their associated permissions. You may only delete roles which are not assigned. Click on the role name to edit that role."));

      $p->add($form = $this->createForm());
      $form->add($tab = new XQuickTable(array('class'=>'roles-table full'),
                                        array("Role", "Description", "Permissions", "# of Users", "")));
      foreach ($roles as $i => $role) {
        $perms = new XUl(array('class'=>'role-permissions'));
        foreach ($role->getPermissions() as $perm)
          $perms->add(new XLi($perm));

        $count = count($role->getAccounts());
        $del = "";
        if ($count == 0)
          $del = new FCheckbox('delete[]', $role->id, "");

        $tab->addRow(array(
                       new XA(sprintf('/roles?id=%s', $role->id), $role),
                       $perm->description,
                       $perms,
                       new XTD(array('class'=>'right'), $count),
                       $del
                     ),
                     array('class' => 'row' . ($i % 2)));
      }

      $form->add(new XSubmitP('edit', "Delete checked", array(), true));
    }
  }

  public function process(Array $args) {
    throw new SoterException("Nothing to process here.");
  }
}
?>