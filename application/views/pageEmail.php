<?php
$CI = &get_instance();
?>

<div>
	<div style="">
		<div style="border-bottom: 1px solid #E5E5E5;  padding: 10px;">
			<img alt="<?php echo SITE_NAME; ?>" src="<?php echo base_url('assets/images/logo.png'); ?>" width="151" height="39">
		</div>
		<div  style="padding: 10px;">
<?php 
$this->load->view($emailView); 
if (isset($url)) {
?>	
			<div style=" background: #F5F5F5; border:1px solid #E5E5E5; border-radius: 5px; padding: 10px;">
				<?php echo sprintf($CI->lang->line('Trouble clicking? Copy and paste this URL into your browser: <br/> %s'), $url); ?>
			</div>
<?php
	}
?>			
		</div>
		<div style="background: #F5F5F5; text-align: center; border-top: 1px solid #E5E5E5; padding: 10px;">
			<?php echo $CI->lang->line('Thank you for using cReader'); ?>
		</div>
	</div>
</div>

