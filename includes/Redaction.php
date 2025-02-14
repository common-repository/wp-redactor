<?php

/**
 * This class is responsible for all redaction functionality.
 */
final class Redaction {

	/**
	 * Returns the content if the current user is assigned a role that
	 * is allowed to read the content, otherwise return a string with
	 * the same number of characters as the content but contains only
	 * underscores and spaces.
	 * @param array $csv A comma separated list of allowed roles that will be compared to the current user's roles
	 * @param string $content The content to redact or display.
	 */
	public function redact($allowedCsv, $who, $when, $content = null) {
		if (null == $content || strlen(trim($content)) == 0) {
			return "";
		}
		if (null == $who) {
			$who = "unknown";
		}
		if (null == $when) {
			$when = "unspecified date";
		}
		$allowed = current_user_can ('administrator') | current_user_can ('editor');
		foreach (explode(',', $allowedCsv) as $allowedRole) {
			$allowed |= current_user_can (trim($allowedRole));
			if ($allowed) {
				break;
			}
		}
		$str = "";
		$style = 'redacted';
		if ($allowed) {
			$style = 'allowed';
			$str = $content;
		}
		for($i = 0; ! $allowed && $i < strlen ( $content ); $i ++) {
			if ($i % 5 == 0 && $i != 0 && $i != strlen ( $content ) - 1) {
				$str .= " "; // add a space every once a while to make sure word wrap is working but avoid putting a space as the last char
			} else {
				$str .= datasync_FULL_BLOCK; // we need some character to show in the HTML, I picked an underscore
			}
		}
		return sprintf("<span class='%s' title='Redacted by %s on %s'>%s</span>", $style, $who, $when, $str);
	}
}