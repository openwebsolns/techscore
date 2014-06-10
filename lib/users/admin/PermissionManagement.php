<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manages (edits, adds, removes) the permisisons in the database
 *
 * Permissions that exist in the database are available to be assigned
 * to users, including admins. To limit what admins can do, therefore,
 * remove a permission from the system.
 */
class PermissionManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Permissions", $user);
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Edit
    // ------------------------------------------------------------
    if (isset($args['id'])) {
      try {
        $permission = DB::$V->reqID($args, 'id', DB::$PERMISSION, "Invalid permission requested.");
        $this->PAGE->addContent($p = new XPort("Edit permission"));
        $p->add($form = $this->createForm());
        $form->add(new FReqItem("ID:", new XStrong(strtoupper($permission->id))));
        $form->add(new FReqItem("Title:", new XTextInput('title', $permission->title)));
        $form->add(new FReqItem("Category:", new XTextInput('category', $permission->category), "Strongly recommend using existing value."));
        $form->add(new FItem("Description:", new XTextArea('description', $permission->description)));

        $form->add($xp = new XSubmitP('edit-permission', "Edit"));
        $xp->add(new XHiddenInput('permission', $permission->id));
        $xp->add(new XA($this->link(), "Cancel"));
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    $available = array_flip(Permission::getPossible());
    $added = DB::getAll(DB::$PERMISSION, new DBCondIn('id', $available));

    $add_port = null;
    if (count($added) < count($available)) {
      $add_port = new XPort("Add Permission");
      $this->PAGE->addContent($add_port);
    }

    // ------------------------------------------------------------
    // Existing permissions
    // ------------------------------------------------------------
    $unadded = array();
    $this->PAGE->addContent($p = new XPort("Existing Permissions"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "Select which of the defined permissions are available in the database."));

    $added = 0;
    $tab = new XQuickTable(array('class' => 'permission-table'), array("", "Title", "Description", "Category"));
    foreach (DB::getAll(DB::$PERMISSION) as $i => $permission) {
      $tab->addRow(array(new FCheckbox('delete[]', $permission->id),
                         new XA($this->link(array('id' => $permission->id)), $permission),
                         $permission->description,
                         $permission->category),
                   array('class' => 'row' . ($i % 2)));
      unset($available[$permission->id]);
      $added++;
    }

    if (count($added) == 0) {
      $form->add(new XP(array('class' => 'warning'), "There are no permissions in the database. This means that only super-users can actually use the application."));
    }
    else {
      $form->add($tab);
      $form->add(new XSubmitP('delete-permissions', "Delete", array(), true));
    }

    // Fill the "Add permissions" port
    if (count($available) == 0) {
      $add_port->add(new XP(array('class' => 'warning'), "There are no available permissions to add."));
    }
    else {
      $add_port->add($form = $this->createForm());
      $form->add(new FReqItem("Permission:", XSelect::fromArray('permission', $available)));
      $form->add(new FReqItem("Title:", new XTextInput('title', "")));
      $form->add(new FReqItem("Category:", new XTextInput('category', ""), "Strongly recommend using existing value."));
      $form->add(new FItem("Description:", new XTextArea('description', "")));
      $form->add(new XSubmitP('add-permission', "Add Permission"));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Add new one
    // ------------------------------------------------------------
    if (isset($args['add-permission'])) {
      $id = DB::$V->reqValue($args, 'permission', Permission::getPossible(), "Invalid permission requested.");
      if (Permission::g($id) !== null)
        throw new SoterException("The requested permission already exists in the system.");

      $perm = new Permission();
      $perm->id = $id;
      $perm->title = DB::$V->reqString($args, 'title', 1, 51, "Invalid/missing title provided.");
      $perm->category = DB::$V->reqString($args, 'category', 1, 41, "Invalid/missing category provided.");
      $perm->description = DB::$V->incString($args, 'description', 1, 16000);
      DB::set($perm);
      Session::pa(new PA(sprintf("Added permission \"%s\" to the database.", $perm)));
    }

    // ------------------------------------------------------------
    // Edit
    // ------------------------------------------------------------
    if (isset($args['edit-permission'])) {
      $perm = DB::$V->reqID($args, 'permission', DB::$PERMISSION, "Invalid permission to edit.");
      $perm->title = DB::$V->reqString($args, 'title', 1, 51, "Invalid/missing title provided.");
      $perm->category = DB::$V->reqString($args, 'category', 1, 41, "Invalid/missing category provided.");
      $perm->description = DB::$V->incString($args, 'description', 1, 16000);
      DB::set($perm);
      Session::pa(new PA(sprintf("Edited permission \"%s\".", $perm)));
    }

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete-permissions'])) {
      $delete = array();
      foreach (DB::$V->reqList($args, 'delete', null, "No permissions chosen.") as $id) {
        $perm = Permission::g($id);
        if ($perm === null)
          throw new SoterException("Invalid permission ID to delete: " . $id);
        $delete[] = $perm;
      }
      if (count($delete) == 0)
        throw new SoterException("No permissions specified.");

      foreach ($delete as $perm)
        DB::remove($perm);

      if (count($delete) <= 3)
        Session::pa(new PA(sprintf("Removed %s from the database.", implode(", ", $delete))));
      else
        Session::pa(new PA(sprintf("Removed %d permission from the database.", count($delete))));
    }
  }
}
?>