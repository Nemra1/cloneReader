<?php
$siteLogo = config_item('siteLogo');
?>
<div>
	<div style="">
		<div style="border-bottom: 1px solid #E5E5E5;  padding: 10px;">
			<a href="<?php echo base_url(); ?>">
				<img alt="<?php echo config_item('siteName'); ?>" src="<?php echo base_url('assets/images/logo.png'); ?>" width="<?php echo $siteLogo['w']; ?>" height="<?php echo $siteLogo['h']; ?>">
			</a>
		</div>
		<div  style="padding: 10px;">
<?php
$this->load->view($view);
if (isset($url)) {
?>
			<div style=" background: #F5F5F5; border:1px solid #E5E5E5; border-radius: 5px; padding: 10px;">
				<?php echo sprintf(lang('Trouble clicking? Copy and paste this URL into your browser: <br/> %s'), $url); ?>
			</div>
<?php
}
?>
		</div>
		<div style="background: #F5F5F5; text-align: center; border-top: 1px solid #E5E5E5; padding: 10px;">
			<?php echo sprintf(lang('Thank you for using %s'), config_item('siteName')); ?>
		</div>
	</div>
</div>
