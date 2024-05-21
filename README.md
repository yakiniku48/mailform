# YAKINIKU48 MailForm

YAKINIKU48 MailForm は、カスタマイズ可能なメールフォームを作成するための WordPress プラグインです。

## 機能

- メールフォームの作成
- フォームバリデーション
- 管理者への通知メールの送信
- ユーザーへの確認メールの送信
- テーマ内でのカスタマイズ可能

## 使用方法

### 1. テーマファイルの設定

フォームページに `class-mailform.php` ファイルを作成し、以下のコードを追加します。

```php
<?php
if ( class_exists( 'YAKINIKU48_MailForm' ) && ! class_exists( 'MailForm' ) ) {
	class MailForm extends YAKINIKU48_MailForm
	{
	}
}
```

### 2. フォームページの作成
フォームを表示するページに以下のコードを追加します。

```php
<?php
require locate_template( 'class-mailform.php' );
$mailform = new MailForm();
$mailform->do_form();
?>
<form method="post" id="form-contact">
	<p>
		<label for="your-name">お名前<br>
			<?php echo $mailform->form_input( 'your-name' ); ?>
		</label>
	</p>
	<p>
		<label for="your-email">メールアドレス<br>
			<?php echo $mailform->form_input( 'your-email' ); ?>
		</label>
	</p>
	<p>
		<label for="your-message">お問い合わせ内容<br>
			<?php echo $mailform->form_textarea( 'your-message' ); ?>
		</label>
	</p>
	<p><input value="send" type="submit"></p>
</form>
```

### 3. 確認画面の作成
確認画面を表示するページに以下のコードを追加します。

```php
<?php
require locate_template( 'class-mailform.php' );
$mailform = new MailForm();
$mailform->do_confirm();
?>
<form method="post">
	<?php echo $mailform->hidden_inputs(); ?>
	<p>お名前: <?php echo $mailform->get( 'your-name' ); ?></p>
	<p>メールアドレス: <?php echo $mailform->get( 'your-email' ); ?></p>
	<p>お問い合わせ内容:<br>
		<?php echo nl2br( $mailform->get( 'your-message' ) ); ?>
	</p>
	<p>
		<button type="submit" name="action" value="back">戻る</button>
		<button type="submit" name="action" value="send">送信する</button>
	</p>
</form>
```

## カスタマイズ
class-mailform.php ファイルを編集することで、送信先やフォームの項目をオーバーライドできます。

### 例）管理者宛メールの設定変更
```php
public $admin_to = 'contact@example.com';
public $admin_subject = 'メールフォームからのお問い合わせ';
```

### 例）パスの変更
```php
public $path = [
	'form' => 'contact',
	'confirm' => 'contact/confirm',
	'thanks' => 'contact/thanks',
];
```

## ライセンス
このプラグインは GPL-2.0 ライセンスのもとで公開されています。

## 作者
Hideyuki Motoo
https://github.com/yakiniku48/