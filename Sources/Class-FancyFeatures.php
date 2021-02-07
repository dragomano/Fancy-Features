<?php

/**
 * Class-FancyFeatures.php
 *
 * @package Fancy Features
 * @link https://dragomano.ru/mods/fancy-features
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2010-2021 Bugo
 * @license https://opensource.org/licenses/MIT MIT
 *
 * @version 1.9.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class FancyFeatures
{
	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_actions', __CLASS__ . '::actions', false, __FILE__);
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifications', false, __FILE__);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons', false, __FILE__);
		add_integration_function('integrate_prepare_display_context', __CLASS__ . '::prepareDisplayContext', false, __FILE__);
		add_integration_function('integrate_bbc_codes', __CLASS__ . '::bbcCodes', false, __FILE__);
		add_integration_function('integrate_buffer', __CLASS__ . '::buffer', false, __FILE__);
	}

	/**
	 * Убираем отключенные пункты меню
	 *
	 * @param array $actions
	 * @return void
	 */
	public static function actions(&$actions)
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
	public static function loadTheme()
	{
		global $modSettings, $db_show_debug, $txt;

		loadLanguage('FancyFeatures/');

		// Режим отладки
		$db_show_debug = !empty($modSettings['fancy_debug_mode']);

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
	public static function menuButtons(&$buttons)
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
	public static function prepareDisplayContext(&$output, &$message)
	{
		global $modSettings;

		if (!empty($modSettings['fancy_highlight_admin_posts']) && !empty($output['member']['group_id']) && $output['member']['group_id'] == 1) {
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
	 * Небольшая правка для тега time
	 *
	 * @param array $codes
	 * @return void
	 */
	public static function bbcCodes(&$codes)
	{
		foreach ($codes as $tag => $dump) {
			if ($dump['tag'] == 'time') {
				$codes[$tag]['validate'] = function(&$tag, &$data, $disabled) {
					if (is_numeric($data))
						$data = timeformat($data);

					$tag['content'] = '<strong>$1</strong>';
				};

				break;
			}
		}
	}

	/**
	 * Производим некоторые замены в буфере страницы
	 *
	 * @param string $buffer
	 * @return string
	 */
	public static function buffer($buffer)
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
	public static function adminAreas(&$admin_areas)
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
	public static function adminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$settings_search[] = array(__CLASS__ . '::settings', 'area=modsettings;sa=fancy_features');
	}

	/**
	 * Указываем функцию с настройками мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifications(&$subActions)
	{
		$subActions['fancy_features'] = array(__CLASS__, 'settings');
	}

	/**
	 * Выводим настройки мода
	 *
	 * @param bool $return_config
	 *
	 * @return array|void
	 */
	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl;

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
			saveDBSettings($save_vars);

			redirectexit('action=admin;area=modsettings;sa=fancy_features');
		}

		prepareDBSettingContext($config_vars);
	}
}
