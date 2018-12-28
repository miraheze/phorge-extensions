<?php

// Source: https://raw.githubusercontent.com/wikimedia/phabricator-extensions/wmf/stable/src/panel/ProjectOpenTasksProfileMenuItem.php

final class ProjectOpenTasksProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'custom.open-tasks';

  public function getMenuItemTypeIcon() {
    return 'fa-anchor';
  }

  public function getDefaultName() {
    return pht('Open Tasks');
  }

  public function getMenuItemTypeName() {
    return pht('Link to Open Tasks');
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->getDefaultName();
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $object = $config->getProfileObject();

    $href = '/maniphest/?project='.$object->getPHID().'&statuses=open()&group=none&order=newest#R';

    $item = $this->newItem()
      ->setHref($href)
      ->setName($this->getDisplayName($config))
      ->setIcon('fa-anchor');

    return array(
      $item,
    );
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorInstructionsEditField())
        ->setValue(
          pht(
            'This adds a link to search maniphest for open tasks which are '.
            "tagged with this project.\n\n")),
    );
  }

}
