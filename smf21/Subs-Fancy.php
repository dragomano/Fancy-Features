<?php

/**
 * Subs-Fancy.php
 *
 * @package Fancy Features
 * @link https://dragomano.ru/mods/fancy-features
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2010-2020 Bugo
 * @license https://opensource.org/licenses/MIT MIT
 *
 * @version 1.8
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Подключаем используемые хуки
 *
 * @return void
 */
function fancy_hooks()
{
	add_integration_function('integrate_actions', 'fancy_actions', false, __FILE__);
	add_integration_function('integrate_load_theme', 'fancy_load_theme', false, __FILE__);
	add_integration_function('integrate_admin_areas', 'fancy_admin_areas', false, __FILE__);
	add_integration_function('integrate_admin_search', 'fancy_admin_search', false, __FILE__);
	add_integration_function('integrate_modify_modifications', 'fancy_modifications', false, __FILE__);
	add_integration_function('integrate_menu_buttons', 'fancy_menu_buttons', false, __FILE__);
	add_integration_function('integrate_prepare_display_context', 'fancy_prepare_display_context', false, __FILE__);
	add_integration_function('integrate_buffer', 'fancy_buffer', false, __FILE__);
}

/**
 * Undocumented function
 *
 * @param  array $actions
 * @return void
 */
function fancy_actions(&$actions)
{
	global $modSettings;

	if (!empty($modSettings['fancy_hide_help_link']))
		unset($actions['help']);

	if (!empty($modSettings['fancy_hide_register_button']))
		unset($actions['signup'], $actions['signup2']);
}

/**
 * Подключаем языковой файл, а также некоторые опции
 *
 * @return void
 */
function fancy_load_theme()
{
	global $modSettings, $db_show_debug, $txt;

	loadLanguage('Fancy');

	// Режим отладки
	if (!empty($modSettings['fancy_debug_mode'])) {
		$db_show_debug = true;

		if (!function_exists('debug')) {
			/**
			 * Вспомогательная функция для удобного просмотра переменных
			 *
			 * @param array $arr
			 * @return void
			 */
			function debug(...$data)
			{
				foreach ($data as $var) {
					echo '<pre>' . print_r($var, true) . '</pre>';
				}
			}
		}
	}

	// Response Prefix Hide
	if (!empty($modSettings['fancy_hide_response_prefix']))
		$txt['response_prefix'] = '';
}

/**
 * Подключаем различные фичи
 *
 * @param array $buttons
 * @return void
 */
function fancy_menu_buttons(&$buttons)
{
	global $modSettings, $txt, $context, $scripturl, $user_profile;

	// Отключаем блокировку регистрации людей с одного и того же устройства
	$modSettings['disableRegisterCheck'] = !empty($modSettings['fancy_disable_register_check']);

	// Управление отображением кнопки «Регистрация»
	if (!empty($modSettings['fancy_hide_register_button'])) {
		unset($buttons['signup']);
		$txt['welcome_guest_register'] = $txt['welcome_guest'];
	}

	// Добавление подпункта «Расширенные настройки» в раскрывающийся список «Админка» в главном меню
	if ($context['allow_admin']) {
		$counter = 0;
		foreach ($buttons['admin']['sub_buttons'] as $area => $dummy) {
			$counter++;
			if ($area == 'packages')
				break;
		}

		$buttons['admin']['sub_buttons'] = array_merge(
			array_slice($buttons['admin']['sub_buttons'], 0, $counter, true),
			array(
				'modsettings' => array(
					'title' => isset($txt['fancy_ext']) ? $txt['fancy_ext'] : $txt['settings'],
					'href'  => $scripturl . '?action=admin;area=modsettings;sa=fancy_features',
					'show'  => allowedTo('admin_forum')
				)
			),
			array_slice($buttons['admin']['sub_buttons'], $counter, null, true)
		);
	}

	if (!empty($_REQUEST['topic']) && !empty($user_profile)) {
		foreach (array_keys($user_profile) as $user_id) {
			// Прячем адрес сайта пользователя (если страницу смотрит гость)
			if (!empty($modSettings['fancy_hide_website']) && $context['user']['is_guest'])
				$user_profile[$user_id]['website_url'] = '';

			// У названий групп в сообщениях тот же цвет, что и в настройках этих групп
			if (!empty($modSettings['fancy_color_groups']) && !empty($user_profile[$user_id]['member_group_color']))
				$user_profile[$user_id]['member_group'] = '<span style="color: ' . $user_profile[$user_id]['member_group_color'] . '">' . $user_profile[$user_id]['member_group'] . '</span>';

			// Прячем иконку «E-mail» от всех, кроме админа
			if (!empty($modSettings['fancy_hide_email_icon']) && !$context['user']['is_admin'])
				$user_profile[$user_id]['email_address'] = '';
		}
	}
}

/**
 * Настраиваем сообщения в соответствии с настройками мода
 *
 * @param array $output
 * @param array $message
 * @return void
 */
function fancy_prepare_display_context(&$output, &$message)
{
	global $modSettings;

	if (!empty($modSettings['fancy_highlight_admin_posts']) && $output['member']['group_id'] == 1) {
		$output['css_class'] .= ' sticky';
	}

	if (!empty($modSettings['fancy_lock_post']) && !empty($message['modified_name']) && $message['modified_name'] != $message['poster_name']) {
		$output['can_modify'] = false;
		$output['quickbuttons']['quick_edit']['show'] = false;
		$output['quickbuttons']['more']['modify']['show'] = false;
	}

	if (!empty($modSettings['fancy_hide_last_change']) && !empty($output['modified']['time']) && !empty($message['modified_name']) && $message['modified_name'] != $message['poster_name']) {
		$output['modified']['name'] = '';
	}
}

/**
 * Производим некоторые замены в буфере страницы
 *
 * @param string $buffer
 * @return string
 */
function fancy_buffer($buffer)
{
	global $modSettings, $scripturl, $txt;

	if (isset($_REQUEST['xml']))
		return $buffer;

	$replacements = array();

	// Прячем IP
	if (!empty($modSettings['fancy_hide_ip']) && !empty($_REQUEST['topic'])) {
		$poster_ip_text = '<li class="poster_ip">';
		$replacements[$poster_ip_text] = '<li class="poster_ip" style="display: none">';
	}

	// Управление отображением ссылки «Помощь»
	if (!empty($modSettings['fancy_hide_help_link'])) {
		$help_link = '<a href="' . $scripturl . '?action=help">' . $txt['help'] . '</a> ' . (!empty($modSettings['requireAgreement']) ? '| ' : ' |');
		$replacements[$help_link] = '';
	}

	// Убираем строчку «Пользователи за последние ... минут» на главной странице
	if (!empty($modSettings['fancy_hide_users_active_msg'])) {
		$last_users = sprintf($txt['users_active'], $modSettings['lastActive']) . ': ';
		$replacements[$last_users] = '';
	}

	return str_replace(array_keys($replacements), array_values($replacements), $buffer);
}

/**
 * Подключаем вкладку с настройками мода в админке
 *
 * @param array $admin_areas
 * @return void
 */
function fancy_admin_areas(&$admin_areas)
{
	global $txt;

	$admin_areas['config']['areas']['modsettings']['subsections']['fancy_features'] = array($txt['fancy']);
}

/**
 * Дополняем быстрый поиск настроек в админке
 *
 * @param array $language_files
 * @param array $include_files
 * @param array $settings_search
 * @return void
 */
function fancy_admin_search(&$language_files, &$include_files, &$settings_search)
{
	$settings_search[] = array('fancy_settings', 'area=modsettings;sa=fancy_features');
}

/**
 * Указываем функцию с настройками мода
 *
 * @param array $subActions
 * @return void
 */
function fancy_modifications(&$subActions)
{
	$subActions['fancy_features'] = 'fancy_settings';
}

/**
 * Выводим настройки мода
 *
 * @param bool $return_config
 *
 * @return array|void
 */
function fancy_settings($return_config = false)
{
	global $context, $txt, $scripturl, $modSettings;

	$context['page_title']     = $txt['fancy'];
	$context['settings_title'] = $txt['fancy_ext'];
	$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=fancy_features';
	$context[$context['admin_menu_name']]['tab_data']['tabs']['fancy_features'] = array('description' => $txt['fancy_desc']);

	$config_vars = array(
		array('check', 'fancy_color_groups'),
		array('check', 'fancy_highlight_admin_posts'),
		array('check', 'fancy_lock_post'),
		array('check', 'fancy_hide_last_change'),
		array('check', 'fancy_hide_users_active_msg'),
		array('check', 'fancy_hide_website'),
		array('check', 'fancy_hide_email_icon'),
		array('check', 'fancy_hide_response_prefix'),
		array('check', 'fancy_hide_ip'),
		array('check', 'fancy_hide_help_link'),
		array('check', 'fancy_hide_register_button'),
		array('check', 'fancy_disable_register_check', 'help' => $txt['fancy_disable_register_check_help']),
		array('check', 'fancy_debug_mode', 'help' => $txt['fancy_debug_mode_help'])
	);

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save'])) {
		checkSession();

		$save_vars = $config_vars;
		if (!empty($modSettings['fancy_show_memory_usage']) && empty($modSettings['timeLoadPageEnable'])) {
			$_POST['timeLoadPageEnable'] = 1;
			$save_vars[] = ['int', 'timeLoadPageEnable'];
		}

		saveDBSettings($save_vars);
		redirectexit('action=admin;area=modsettings;sa=fancy_features');
	}

	prepareDBSettingContext($config_vars);
}
