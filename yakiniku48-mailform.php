<?php
/*
Plugin Name: YAKINIKU48 MailForm
Plugin URI:  https://github.com/yakiniku48/mailform
Description: YAKINIKU48 MailForm のコアファイルです。
Version:     0.2.0
Author:      Hideyuki Motoo
Author URI:  https://nearly.jp/
Update URI:  yakiniku48-mailform
*/

if ( ! class_exists( 'YAKINIKU48_MailForm' ) ) {
	class YAKINIKU48_MailForm
	{
		public $validation_rules = [
			'your-name' => [
				'type' => 'text',
				'label' => 'お名前',
				'rules' => [
					'required',
				],
				'placeholder' => 'フォーム　太郎',
			],
			'your-email' => [
				'type' => 'email',
				'label' => 'メールアドレス',
				'rules' => [
					'required',
					'valid_email',
					'match',
				],
			],
			'your-email-conf' => [
				'type' => 'email',
				'label' => 'メールアドレス（確認）',
				'rules' => [
					'required',
				],
			],
			'your-service' => [
				'label' => 'あなたの好きなサービス',
				'rules' => [
					'required',
				],
				'choices' => [
					'GMail',
					'Notion',
					'Slack',
				],
				'default' => [
				],
			],
			'your-color' => [
				'label' => 'あなたの好きな色',
				'rules' => [
					'required',
				],
				'choices' => [
					'RED' => '赤',
					'GREEN' => '緑',
					'BLUE' => '青',
				],
				'default' => [
					'RED',
				],
			],
			'your-language' => [
				'label' => 'あなたの得意な言語',
				'rules' => [
					'required',
				],
				'choices' => [
					'HTML/CSS',
					'JavaScript',
					'PHP',
				],
				'default' => [
				],
				'placeholder' => '選択してください',
			],
			'your-message' => [
				'label' => 'お問い合わせ内容',
				'rules' => [
				],
			],
		];

		public $path = [
			'form' => 'form',
			'confirm' => 'confirm',
			'thanks' => 'thanks',
		];

		public $mail_from_name = 'WordPress';
		public $mail_from_email = 'wordpress'; // @ 以降はhome_urlから自動的に取得されます
		public $admin_to = 'wordpress@example.com';
		public $admin_subject = 'ウェブからお問い合わせがありました';
		public $admin_body = <<<EOM
{your-name}様からお問い合わせがありました

ご対応のほどよろしくお願いします

- お名前
-- {your-name}
- メールアドレス
-- {your-email}
- あなたの好きなサービス
-- {your-service}
- あなたの好きな色
-- {your-color}
- あなたの得意な言語
-- {your-language}

お問い合わせ内容:
{your-message}

EOM;

		public $reply_subject = 'お問い合わせありがとうございます';
		public $reply_to_key = 'your-email';
		public $reply_body = <<<EOM
{your-name}様

以下の内容で承りました。

- お名前
-- {your-name}
- メールアドレス
-- {your-email}
- あなたの好きなサービス
-- {your-service}
- あなたの好きな色
-- {your-color}
- あなたの得意な言語
-- {your-language}

お問い合わせ内容:
{your-message}

EOM;

		public $post = [];
		public $cookie = [];

		public $validation_errors = [];

		public $multiple_values_separator = ', ';
		public $empty_value = '';

		public $grecaptha_sitekey = '';
		public $grecaptha_secretkey = '';
		public $grecaptha_score = false;

		public function __construct()
		{
			$sitename = str_replace( 'www.', '', wp_parse_url( network_home_url(), PHP_URL_HOST ) );
			$this->mail_from_email .= '@' . $sitename;

			add_filter( 'wp_mail_from', function ( $from_email ) {
				return $this->mail_from_email;
			} );
			add_filter( 'wp_mail_from_name', function ( $from_name ) {
				return $this->mail_from_name;
			} );

			foreach ( $this->validation_rules as $key => $validation_rule ) {
				$this->post[ $key ] = false;
				if ( $input_post = filter_input( INPUT_POST, $key ) ) {
					$this->post[ $key ] = $input_post;
				}
				if ( $input_post = filter_input( INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ) ) {
					$this->post[ $key ] = $input_post;
				}
			}

			$cookie = [];
			if ( $input_cookie = filter_input( INPUT_COOKIE, 'post_values' ) ) {
				$cookie = json_decode( $input_cookie, true );
			}
			foreach ( $this->validation_rules as $key => $validation_rule ) {
				$this->cookie[ $key ] = ( ! empty( $cookie[ $key ] ) ) ? $cookie[ $key ] : false;
			}

			if ( $validation_errors = filter_input( INPUT_COOKIE, 'validation_errors' ) ) {
				$this->validation_errors = json_decode( $validation_errors, true );
			}
		}

		public function validate()
		{
			foreach ( $this->validation_rules as $key => $validation_rule ) {
				// required
				if ( in_array( 'required', $validation_rule['rules'] ) ) {
					if ( ! $this->post[ $key ] ) {
						$this->validation_errors[ $key ][] = '「' . $validation_rule['label'] . '」は必須です';
					}
				}

				// valid_email
				if ( in_array( 'valid_email', $validation_rule['rules'] ) ) {
					if ( $this->post[ $key ] && ! is_email( $this->post[ $key ] ) ) {
						$this->validation_errors[ $key ][] = '「' . $validation_rule['label'] . '」には有効なメールアドレスを入力してください';
					}
				}

				// match : match the value of ${key}-conf
				if ( in_array( 'match', $validation_rule['rules'] ) ) {
					if ( $this->post[ $key ] && $this->post[ $key ] !== $this->post[ $key . '-conf' ] ) {
						$this->validation_errors[ $key ][] = '「' . $validation_rule['label'] . '」が「' . $this->validation_rules[ $key . '-conf' ]['label'] . '」と一致しません';
					}
				}
			}

			// WP Nonce
			if ( ! filter_input( INPUT_POST, 'yakiniku48_nonce' ) || ! wp_verify_nonce( $_POST['yakiniku48_nonce'], 'yakiniku48_form_send' ) ) {
				$this->validation_errors['yakiniku48_nonce'] = [ 'Failed to verify your nonce.' ];
			}

			// Google reCAPTCHA
			if ( $this->grecaptha_sitekey && $this->grecaptha_secretkey ) {
				$captcha_response = filter_input( INPUT_POST, 'g-recaptcha-response' );
				$verify_response = file_get_contents( 'https://www.google.com/recaptcha/api/siteverify?secret=' . $this->grecaptha_secretkey . '&response=' . $captcha_response );

				$response = json_decode( $verify_response, true );
				if ( $response['success'] ) {
					// success
					if ( ! empty( $response['score'] ) ) {
						$this->grecaptha_score = $response['score'];
					} else {
						$this->validation_errors['grecaptha'] = [ 'Failed to retrieve reCAPTCHA score.' ];
					}
				} else {
					$this->validation_errors['grecaptha'] = $response['error-codes'];
				}
			}

			return empty( $this->validation_errors );
		}

		public function do_form()
		{
			$http_method = strtoupper( $_SERVER['REQUEST_METHOD'] );
			if ( $http_method === 'POST' ) {
				if ( $this->validate() ) {
					$this->redirect( $this->path['confirm'] );
					exit;
				}
			}
		}

		public function do_confirm()
		{
			$action = filter_input( INPUT_POST, 'action' );
			if ( $action === 'back' ) {
				$this->redirect( $this->path['form'] );
				exit;
			}
			if ( $action === 'send' ) {
				if ( $this->validate() ) {
					$admin_body = $this->admin_body;
					$reply_body = $this->reply_body;
					foreach ( array_keys( $this->validation_rules ) as $key ) {
						$value = $this->post[ $key ];
						if ( is_array( $value ) ) {
							$value = implode( $this->multiple_values_separator, $value );
						}
						if ( ! $value ) $value = $this->empty_value;
						$value = sanitize_textarea_field( $value );
						$admin_body = str_replace( '{' . $key . '}', $value, $admin_body );
						$reply_body = str_replace( '{' . $key . '}', $value, $reply_body );
					}
					wp_mail( $this->admin_to, $this->admin_subject, $this->replace_message( $admin_body ) );
					wp_mail( $this->post[ $this->reply_to_key ], $this->reply_subject, $this->replace_message( $reply_body ) );

					$this->redirect( $this->path['thanks'] );
					exit;
				} else {
					$this->redirect( $this->path['form'] );
					exit;
				}
			}

			if ( empty( $this->cookie ) ) {
				wp_safe_redirect( home_url( $this->path['form'] ) );
				exit;
			}
		}

		public function replace_message( $message )
		{
			return $message;
		}

		public function redirect( $path )
		{
			setcookie( 'post_values', json_encode( $this->post ), time() + 15, COOKIEPATH, COOKIE_DOMAIN, true, true );
			setcookie( 'validation_errors', json_encode( $this->validation_errors ), time() + 15, COOKIEPATH, COOKIE_DOMAIN, true, true );
			wp_safe_redirect( home_url( $path ) );
			exit();
		}

		public function error( $key )
		{
			if ( ! empty( $this->validation_errors[ $key ] ) ) {
				if ( is_array( $this->validation_errors[ $key ] ) ) {
					return implode( "\n", array_map( [ $this, 'validation_add_affix' ], $this->validation_errors[ $key ] ) );
				} else {
					return $this->validation_add_affix( $this->validation_errors[ $key ] );
				}
			}
		}

		/**
		 * Check if any validation errors exist.
		 *
		 * @return bool True if there are validation errors.
		 */
		public function has_validation_errors()
		{
			return ! empty( $this->validation_errors );
		}

		public function validation_add_affix( $string )
		{
			return '<p class="y48mf-error">' . $string . '</p>';
		}

		/**
		 * Template tags
		 */
		public function get( $key, $sanitize = true )
		{
			if ( $this->post[ $key ] ) {
				$post = $this->post[ $key ];
				if ( is_array( $post ) ) {
					$post = implode( $this->multiple_values_separator, $post );
				}
				return $sanitize ? esc_html( $post ) : $post;
			}

			if ( $this->cookie[ $key ] ) {
				$cookie = $this->cookie[ $key ];
				if ( is_array( $cookie ) ) {
					$cookie = implode( $this->multiple_values_separator, $cookie );
				}
				return $sanitize ? esc_html( $cookie ) : $cookie;
			}

			return false;
		}

		public function hidden_inputs()
		{
			$output = '';
			if ( ! empty( $this->cookie ) ) {
				foreach ( $this->cookie as $post_key => $post_value ) {
					if ( is_array( $post_value ) ) {
						foreach ( $post_value as $value ) {
							$output .= '<input type="hidden" name="' . esc_attr( $post_key ) . '[]" value="' . esc_attr( $value ) . '">';
						}
					} else {
						$output .= '<input type="hidden" name="' . esc_attr( $post_key ) . '" value="' . esc_attr( $post_value ) . '">';
					}
				}
			}
			return $output;
		}

		public function form_nonce() {
			ob_start();
			wp_nonce_field( 'yakiniku48_form_send', 'yakiniku48_nonce' );
			echo $this->error( 'yakiniku48_nonce' );
			return ob_get_clean();
		}

		public function form_input( $key ) {
			ob_start();
			?>
			<input type="<?php echo $this->validation_rules[ $key ]['type']; ?>" name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="y48mf-input" value="<?php echo $this->get( $key ); ?>" <?php
				if ( ! empty( $this->validation_rules[ $key ]['placeholder'] ) ) {
					echo 'placeholder="' . esc_attr( $this->validation_rules[ $key ]['placeholder'] ) . '"';
				}
			?>>
			<?php echo $this->error( $key ); ?>
			<?php
			return ob_get_clean();
		}

		public function form_textarea( $key ) {
			ob_start();
			?>
			<textarea name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="y48mf-textarea" <?php
				if ( ! empty( $this->validation_rules[ $key ]['placeholder'] ) ) {
					echo 'placeholder="' . esc_attr( $this->validation_rules[ $key ]['placeholder'] ) . '"';
				}
			?>><?php echo $this->get( $key ); ?></textarea>
			<?php echo $this->error( $key ); ?>
			<?php
			return ob_get_clean();
		}

		public function form_checkbox( $key ) {
			$choices_is_assoc = ( $this->validation_rules[ $key ]['choices'] !== array_values( $this->validation_rules[ $key ]['choices'] ) );
			$post_values = $this->get_post_values( $key );
			ob_start();
			$index = 0;
			?>
			<?php foreach ( $this->validation_rules[ $key ]['choices'] as $value => $label ) : $value = $choices_is_assoc ? $value : $label; ?>
				<label class="y48mf-label">
					<input type="checkbox" name="<?php echo $key; ?>[]" id="<?php echo $key; ?>:<?php echo $index++; ?>" class="y48mf-checkbox" value="<?php echo esc_attr( $value ); ?>" <?php
						if ( $post_values && in_array( $value, $post_values ) ) {
							echo 'checked="checked"';
						} elseif ( ! empty( $this->validation_rules[ $key ]['default'] ) && in_array( $value, $this->validation_rules[ $key ]['default'] ) ) {
							echo 'checked="checked"';
						}
					?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
			<?php echo $this->error( $key ); ?>
			<?php
			return ob_get_clean();
		}

		public function form_radio( $key ) {
			$choices_is_assoc = ( $this->validation_rules[ $key ]['choices'] !== array_values( $this->validation_rules[ $key ]['choices'] ) );
			$post_values = $this->get_post_values( $key );
			ob_start();
			$index = 0;
			?>
			<?php foreach ( $this->validation_rules[ $key ]['choices'] as $value => $label ) : $value = $choices_is_assoc ? $value : $label; ?>
				<label class="y48mf-label">
					<input type="radio" name="<?php echo $key; ?>" id="<?php echo $key; ?>:<?php echo $index++; ?>" class="y48mf-radio" value="<?php echo esc_attr( $value ); ?>" <?php
						if ( $post_values && in_array( $value, $post_values ) ) {
							echo 'checked="checked"';
						} elseif ( ! empty( $this->validation_rules[ $key ]['default'] ) && in_array( $value, $this->validation_rules[ $key ]['default'] ) ) {
							echo 'checked="checked"';
						}
					?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
			<?php echo $this->error( $key ); ?>
			<?php
			return ob_get_clean();
		}

		public function form_select( $key ) {
			$choices_is_assoc = ( $this->validation_rules[ $key ]['choices'] !== array_values( $this->validation_rules[ $key ]['choices'] ) );
			$post_values = $this->get_post_values( $key );
			ob_start();
			?>
			<select name="<?php echo $key; ?>" id="<?php echo $key; ?>" class="y48mf-select">
				<?php if ( ! empty( $this->validation_rules[ $key ]['placeholder'] ) ) : ?>
					<option value=""><?php echo esc_html( $this->validation_rules[ $key ]['placeholder'] ); ?></option>
				<?php endif; ?>
				<?php foreach ( $this->validation_rules[ $key ]['choices'] as $value => $label ) : $value = $choices_is_assoc ? $value : $label; ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php
						if ( $post_values && in_array( $value, $post_values ) ) {
							echo 'selected="selected"';
						} elseif ( ! empty( $this->validation_rules[ $key ]['default'] ) && in_array( $value, $this->validation_rules[ $key ]['default'] ) ) {
							echo 'selected="selected"';
						}
					?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php echo $this->error( $key ); ?>
			<?php
			return ob_get_clean();
		}

		public function form_recaptcha( $form_id ) {
			if ( $this->grecaptha_sitekey && $this->grecaptha_secretkey ) {
				?>
				<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
				<input type="hidden" name="action" value="">
				<script src="https://www.google.com/recaptcha/api.js?render=<?php echo $this->grecaptha_sitekey; ?>"></script>
				<script>
				(function () {
					'use strict';
					document.getElementById('<?php echo $form_id; ?>').addEventListener('submit', function (e) {
						e.preventDefault();
						grecaptcha.ready(function () {
							grecaptcha.execute('<?php echo $this->grecaptha_sitekey; ?>', {action: 'submit'}).then(function (token) {
								document.getElementById('g-recaptcha-response').value = token;
								document.getElementById('<?php echo $form_id; ?>').submit();
							});
						});
					});
				})();
				document.addEventListener('DOMContentLoaded', function () {
					document.querySelectorAll('button[type="submit"]').forEach(function (elm) {
						elm.addEventListener('click', function (e) {
							document.querySelector('input[name="action"]').value = e.target.value;
						});
					});
				});
				</script>
				<?php
				echo $this->error( 'grecaptha' );
			}
		}

		public function get_post_values( $key ) {
			$post_values = $this->post[ $key ];
			if ( empty( $post_values ) && ! empty( $this->cookie[ $key ] ) ) {
				$post_values = $this->cookie[ $key ];
			}
			if ( empty( $post_values ) ) {
				return [];
			}
			return (array) $post_values;
		}
	}
}

add_action( 'template_redirect', function () {
	setcookie( 'post_values', '', time() - 1, COOKIEPATH, COOKIE_DOMAIN, true, true );
	setcookie( 'validation_errors', '', time() - 1, COOKIEPATH, COOKIE_DOMAIN, true, true );
} );

add_filter( 'update_plugins_yakiniku48-mailform', function ( $update, $plugin_data ) {
	$response = wp_remote_get( 'https://api.github.com/repos/yakiniku48/mailform/releases/latest' );
	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		return $update;
	}

	$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
	$new_version = ( ! empty( $response_body['tag_name'] ) ) ? $response_body['tag_name'] : null;
	$package = ( ! empty( $response_body['assets'][0]['browser_download_url'] ) ) ? $response_body['assets'][0]['browser_download_url'] : null;
	$url = ( ! empty( $response_body['html_url'] ) ) ? $response_body['html_url'] : null;

	return array(
		'version' => $plugin_data['Version'],
		'new_version' => $new_version,
		'package' => $package,
		'url' => $url,
	);
}, 10, 2 );
