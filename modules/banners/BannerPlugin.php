<?php

class BannerPlugin extends PluginAbstract {
  const BANNER_PHP_SCRIPT_TYPE_ID = 300;

  public static function createPlugin(array $config) {
    return new BannerPlugin();
  }

  public function getName() {
    return 'Баннеры';
  }
  public function getVersion() {
    return '1.1.0.';
  }
  public function getVersionDate() {
    return '03.10.2013';
  }
  public function getShortDescription() {
    return 'Общий инструмент, предназначенный для хранения баннеров и учета их посещаемости.';
  }

  public function getParametersValueFromConfig($config, $data) {
    if (isset($config['modules']['ygin.banners'])) return $config['modules']['ygin.banners'];
    return array();
  }
  public function getConfigByParamsValue(array $paramsValue, $data) {
    return array('modules' => array('ygin.banners' => $paramsValue));
  }

  public function install(Plugin $plugin) {
    $plugin->setData(array(
        'id_object_banner_place' => 261,
        'id_object_banner' => 260,
    ));
  }
  public function activate(Plugin $plugin) {
    $data = $plugin->getData();
    $this->createPermission($data['id_object_banner_place'], 'Просмотр списка данных объекта Баннерные места');
    $this->createPermission($data['id_object_banner'], 'Просмотр списка данных объекта Баннеры');
    $this->installAggregateViewsStatisticJob();
    $this->activatePhpScript(self::BANNER_PHP_SCRIPT_TYPE_ID);

    //включаеи видимость SiteModule связанных с баннерами, если таковые имеются
    $idsPhpScript = Yii::app()->db->createCommand()
      ->select('id_php_script')
      ->from('da_php_script')
      ->where('id_php_script_type = '.self::BANNER_PHP_SCRIPT_TYPE_ID)
      ->queryAll();

    if($idsPhpScript) {
      $idsPhpScriptArray = array();
      foreach($idsPhpScript as $idPhpScript) {
        $idsPhpScriptArray[] = $idPhpScript['id_php_script'];
      }
      Yii::app()->db->createCommand()->update('da_site_module',array('is_visible' => 1),array('in','id_php_script',$idsPhpScriptArray));
    }
    $this->updateMenu = true;
  }
  public function deactivate(Plugin $plugin) {
    $data = $plugin->getData();
    Yii::app()->authManager->removeAuthItemObject('list', $data['id_object_banner_place']);
    Yii::app()->authManager->removeAuthItemObject('list', $data['id_object_banner']);
    $this->uninstallAggregateViewStatisticJob();
    $this->deactivatePhpScript(self::BANNER_PHP_SCRIPT_TYPE_ID);

    //выключаеи видимость SiteModule связанных с баннерами, если таковые имеются
    $idsPhpScript = Yii::app()->db->createCommand()
      ->select('id_php_script')
      ->from('da_php_script')
      ->where('id_php_script_type = '.self::BANNER_PHP_SCRIPT_TYPE_ID)
      ->queryAll();

    if($idsPhpScript) {
      $idsPhpScriptArray = array();
      foreach($idsPhpScript as $idPhpScript) {
        $idsPhpScriptArray[] = $idPhpScript['id_php_script'];
      }
      Yii::app()->db->createCommand()->update('da_site_module',array('is_visible' => 0),array('in','id_php_script',$idsPhpScriptArray));
    }

    $this->updateMenu = true;
  }

  public function installAggregateViewsStatisticJob() {
    Yii::app()->db->commandBuilder->createInsertCommand('da_job', array(
      'id_job' => 'job-aggregate-views-statistic',
      'interval_value' => 3600,
      'error_repeat_interval' => 1800,
      'name' => 'Агрегация просмотров баннеров',
      'class_name' => 'ygin.modules.banners.components.AggregateViewsStatistic',
      'active' => 1,
      'max_second_process' => 300,
    ))->execute();
  }

  public function uninstallAggregateViewStatisticJob() {
    Yii::app()->db->commandBuilder->createDeleteCommand('da_job', new CDbCriteria(array(
      'condition' => 'id_job = "job-aggregate-views-statistic"',
    )))->execute();
  }

  public function updatePlugin(Plugin $plugin) {
    if ($plugin->getIsEnabled()) {
      $this->installAggregateViewsStatisticJob();
    }
  }

}