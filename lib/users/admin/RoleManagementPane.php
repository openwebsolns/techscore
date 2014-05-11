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

  protected function fillRoleForm(XForm $form, Role $role) {
    $this->PAGE->head->add(new XScript('text/javascript', null, '
window.addEventListener("load", function(e) {
  var c = document.getElementById("chk-all");
  var r = document.getElementById("perms-list");
  c.onchange = function(e) {
    r.style.display = (c.checked) ? "none" : "block";
  };
  r.style.display = (c.checked) ? "none" : "block";
}, false);
'));

    $form->add(new FReqItem("Name:", new XTextInput('title', $role->title, array('min'=>5, 'max'=>256))));
    $form->add(new FReqItem("Description:", new XTextArea('description', $role->description, array('placeholder'=>"Helpful descriptors help you stay organized."))));
    $form->add(new FItem("All permissions:", new FCheckbox('has_all', 1, "This role has all permissions.", $role->has_all !== null, array('id'=>'chk-all')), "Use sparingly. This grants access to all permissions now, and in the future."));
    $form->add($fi = new FItem("Permissions:", $sel = new XSelectM('permissions[]', array('size'=>10))));
    $fi->set('id', 'perms-list');

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
      $groups[$perm->category]->add($opt = new FOption($perm->id, $perm, array('title'=>$perm->description)));
      if (isset($existing[$perm->id]))
	$opt->set('selected', 'selected');
    }
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Add new one?
    // ------------------------------------------------------------
    if (isset($args['create-from'])) {
      $role = DB::$V->incID($args, 'create-from', DB::$ROLE);
      if ($role === null)
	$role = new Role();
      else {
	$role->title .= " (Copy)";
      }

      $this->PAGE->addContent($p = new XPort("Add role"));
      $p->add($form = $this->createForm());
      $this->fillRoleForm($form, $role);
      $form->add($xp = new XSubmitP('add', "Add Role"));
      $xp->add(" ");
      $xp->add(new XA('/roles', "Cancel"));

      return;
    }

    // ------------------------------------------------------------
    // Edit existing?
    // ------------------------------------------------------------
    if (isset($args['id'])) {
      try {
        $role = DB::$V->reqID($args, 'id', DB::$ROLE, "Invalid role requested.");
        $this->PAGE->addContent($p = new XPort("Edit role"));
        $p->add($form = $this->createForm());

	$users = $role->getAccounts();
	if (count($users) > 0) {
	  $form->add(new XP(array(),
			    array(new XStrong("Note:"), " these permissions will instantly apply to all ", count($users), " users that are associated with it.")));
	}

	$this->fillRoleForm($form, $role);

        $form->add($xp = new XSubmitP('edit', "Save Changes"));
        $xp->add(new XHiddenInput('role', $role->id));
        $xp->add(new XA('/roles', "Cancel"));

        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    $roles = DB::getAll(DB::$ROLE);

    // ------------------------------------------------------------
    // Add a role
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Add a Role"));
    $p->add($form = $this->createForm(XForm::GET));
    if (count($roles) > 0) {
      $form->add(new XP(array(), "Create a new role from scratch or by choosing an existing role as template."));
      $form->add(new FItem("Copy existing:", $sel = new XSelect('create-from')));
      $sel->add(new FOption('new', "[Start from scratch]"));
      foreach ($roles as $role)
	$sel->add(new FOption($role->id, $role, array('title'=>$role->description)));
    }
    else {
      $form->add(new XHiddenInput('create-from', 'new'));
    }
    $form->add(new XSubmitP('go', "Create →"));

    // ------------------------------------------------------------
    // List current roles
    // ------------------------------------------------------------
    if (count($roles) > 0) {
      $this->PAGE->addContent($p = new XPort("Existing roles"));
      $p->add(new XP(array(), "Below is a list of existing roles and their associated permissions. You may only delete roles which are not assigned. Click on the role name to edit that role. On the first column, choose the default role for new accounts."));

      $p->add($form = $this->createForm());
      $form->add($tab = new XQuickTable(array('class'=>'roles-table full'),
                                        array("Default", "Role", "Description", "Permissions", "# of Users", "")));

      foreach ($roles as $i => $role) {
	$perms = "";
	if ($role->has_all)
	  $perms = new XEm("All permissions");
	else {
	  $perms = new XUl(array('class'=>'role-permissions'));
	  foreach ($role->getPermissions() as $perm) {
	    $perms->add(new XLi($perm, array('title' => $perm->description)));
	  }
	}

        $count = count($role->getAccounts());
        $del = "";
        if ($count == 0) {
          $del = new FCheckbox('delete[]', $role->id, "");
	}

        $tab->addRow(array(
		       new FRadio('default-role', $role->id, "", $role->is_default),
                       new XA(sprintf('/roles?id=%s', $role->id), $role),
                       $perm->description,
                       $perms,
                       new XTD(array('class'=>'right'), $count),
                       $del
                     ),
                     array('class' => 'row' . ($i % 2)));
      }

      $form->add(new XSubmitP('set-roles', "Save Changes"));
    }
  }

  public function process(Array $args) {
    if (isset($args['edit']) || isset($args['add'])) {
      $role = new Role();
      $mes = "added";
      if (isset($args['edit'])) {
	$role = DB::$V->reqID($args, 'role', DB::$ROLE, "Invalid role provided.");
	$mes = "updated";
      }
      $role->title = DB::$V->reqString($args, 'title', 5, 256, "Invalid title provided for role.");
      $role->description = DB::$V->reqString($args, 'description', 5, 1000, "Invalid description. Did you include enough?");

      // Duplicate title?
      foreach (DB::getAll(DB::$ROLE) as $other) {
	if ($other->id != $role->id && $other->title == $role->title)
	  throw new SoterException("Duplicate role title provided.");
      }

      $all = DB::$V->incInt($args, 'has_all', 1, 2, null);
      $perms = array();
      if ($all === null) {
	foreach (DB::$V->reqList($args, 'permissions', null, "No list of permissions provided.") as $id) {
	  $perm = DB::get(DB::$PERMISSION, $id);
	  if ($perm === null)
	    throw new SoterException("Invalid permission provided: " . $id);
	  $perms[] = $perm;
	}

	if (count($perms) == 0)
	  throw new SoterException("Roles must have at least one permission set.");
      }
      $role->setHasAll($all !== null);

      DB::set($role);
      $role->setPermissions($perms);
      Session::pa(new PA(sprintf("Role \"%s\" %s.", $role, $mes)));
      $this->redirect('roles');
    }

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $to_delete = array();
      foreach (DB::$V->reqList($args, 'delete', null, "No roles provided.") as $id) {
	$role = DB::get(DB::$ROLE, $id);
	if ($role === null)
	  throw new SoterException("Invalid role provided to delete: " . $id);
	if (count($role->getAccounts()) > 0)
	  throw new SoterException(sprintf("Cannot delete %s because there are accounts associated with it.", $role));
	$to_delete[] = $role;
      }

      foreach ($to_delete as $role)
	DB::remove($role);
      Session::pa(new PA(sprintf("Removed role(s): %s.", implode(", ", $to_delete))));
    }

    // ------------------------------------------------------------
    // Set default
    // ------------------------------------------------------------
    if (isset($args['set-roles'])) {
      $def = DB::$V->reqID($args, 'default-role', DB::$ROLE, "Invalid default role provided.");
      foreach (DB::getAll(DB::$ROLE) as $role) {
	$role->is_default = null;
	if ($role->id == $def->id)
	  $role->is_default = 1;
	DB::set($role);
      }
      Session::pa(new PA(sprintf("Set \"%s\" as the default role for new accounts.", $def)));
    }
  }
}
?>