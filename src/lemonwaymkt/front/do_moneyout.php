<?php 
$GLOBALS['_wallet'] = ($_wallet);

	
function formMoneyout(){
?>
<!-- @TODO create a custom CSS file -->
<style>
<!--
.buttons-set{
padding:10px;
-->
</style>
	<div class="woocommerce">
	<div class="">
        <h3><?php echo __('Do a new moneyout',LEMONWAYMKT_TEXT_DOMAIN) ?></h3>
    </div>

<?php 
	LWMKT()->displayFormMoneyout($GLOBALS['_wallet']->id_lw_wallet);
	
?>
	</div>
<?php 
}