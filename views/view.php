<?php
	// Check to ensure this file is included in Joomla!
	defined('_JEXEC') or die('Restricted access');
	AdminUIHelper::startAdminArea($this);
	$validation_results = json_decode($this->order->validation_results);
	
	// Check to ensure this file is included in Joomla!
	defined ('_JEXEC') or die('Restricted access');
	if(VmConfig::get('usefancy',0)){
		vmJsApi::js( 'fancybox/jquery.fancybox-1.3.4.pack');
		vmJsApi::css('jquery.fancybox-1.3.4');
		$box = "
	//<![CDATA[
		jQuery(document).ready(function($) {
			jQuery('.show_pod').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.fancybox ({ div: '#'+id, content: con });
			});
			jQuery('.show_image').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.fancybox ({ div: '#'+id, content: con });
			});
		});
	//]]>
	";
	} else {
		vmJsApi::js ('facebox');
		vmJsApi::css ('facebox');
		$box = "
	//<![CDATA[
		jQuery(document).ready(function($) {
			jQuery('.show_pod').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.facebox( { div: '#'+id }, 'my-groovy-style');
			});
			jQuery('.show_image').click(function(event) {
				event.preventDefault();
				var id = jQuery(this).attr('rel');
				var con = jQuery('#'+id).html();
				jQuery.facebox( { div: '#'+id }, 'my-groovy-style');
			});
		});
	//]]>
	";
	}

	JHtml::_ ('behavior.formvalidation');
	$document = JFactory::getDocument ();
	$document->addScriptDeclaration ($box);	
?>
<div class="parallel">
	<table width="100%">
		<tbody>
			<tr>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">Status Information:</legend>
						<table>
							<?php
								echo '<tr><td>Waybill '.$this->order->waybill.'</td></tr>'.'<tr><td>Status: '.$this->tracking['status_text'].'</td></tr>';
								echo '<tr><td>Status last updated:'.$this->tracking['updated_time'].' on the '.date("d/M/Y",strtotime($this->tracking['updated_date'])).'</td></tr>';
								if(isset($this->tracking['delivered_at']))
								{
									echo '<tr><td>Delivered at '.date("H:i:s",strtotime($this->tracking['delivered_at'])).' on the '.date("d/M/Y",strtotime($this->tracking['delivered_at']));
								}
								else
								{
									if(isset($this->tracking['eta']))
									{
										echo '<tr><td>Estimated time of delivery: '.date("H:i:s",$this->tracking['eta']).' on the '.date("d/M/Y",$this->tracking['eta']).'</td></tr>';
									}
									else
									{
										echo '<tr><td>Delivery will be before '.$this->tracking['delivery_time'].' on the '.date("d/M/Y",strtotime($this->tracking['delivery_date'])).'</td></tr>';
									}
								}
							?>
						</table>
					</fieldset>
				</td>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">General Information:</legend>
						<table>
							<?php echo '<tr><td>Quoted Weight: '.number_format($validation_results->weight, 2, '.', '').' | Actual Weight: '.number_format($this->tracking['weight'], 2, '.', '').'</td></tr>';?>
							<?php echo '<tr><td>Quoted Vol Weight: '.number_format($validation_results->vol_weight, 2, '.', '').' | Actual Vol Weight: '.number_format($this->tracking['vol_weight'], 2, '.', '').'</td></tr>';?>
							<?php echo '<tr><td>Quoted Price: R'.number_format($validation_results->price->inc_vat, 2, '.', '').' | Actual Price: R'.number_format($this->tracking['total_price']*1.14, 2, '.', '').'</td></tr>';?>
							<?php if(!empty($this->pods)):?>
								<tr>
									<td>
										Proof of delivery: <a href="javascript:void(0);" rel="wrapped_pod" class="show_pod">View POD</a>
										<div id="wrapped_pod" style="display:none;width:620px;height:500px;">
											<iframe src="https://docs.google.com/gview?url=<?php echo $image;?>&embedded=true" style="width:500px; height:400px;" frameborder="0"></iframe>
										</div>
									</td>
								</tr>
							<?php endif;?>
							<?php if(!empty($this->image_list)):?>
								<tr>
									<td>
										Images (<?php echo count($this->image_list);?>):
										<?php
											$count=1;
											foreach($this->image_list as $image)
											{
												echo ' <a href="javascript:void(0);" rel="image_'.$count.'" class="show_image">Image '.$count.'</a><div id="image_'.$count.'" style="display:none;"><img src="'.$image.'"/></div>';
												$count++;
											}
										?>
									</td>
								</tr>
							<?php endif;?>
						</table>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="parallel">
	<table width="100%">
		<tbody>
			<tr>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">Collection Address:</legend>
						<?php if(isset($this->collection_address['nice_address']) && $this->collection_address['nice_address'] != "") {echo '<p>'.$this->collection_address['nice_address'].'</p>';}?>
						<?php $collection_count = 1;
						foreach($this->collection_contacts as $contact)
						{
							if(isset($contact['nice_contact']) && $contact['nice_contact'] != "")
							{
								if($collection_count == 1)
								{
									echo '<b>Contacts:</b><br />'.$contact['nice_contact'].'<br />';
								}
								else if($collection_count != count($this->collection_contacts))
								{
									echo $contact['nice_contact'].'<br />';
								}

								else
								{
									echo $contact['nice_contact'];
								}
							}
							$collection_count++;
						}
						?>
					</fieldset>
				</td>
				<td width="50%">
					<fieldset class="parallel_target" style="background-color: rgb(246, 246, 246);">
						<legend style="font-size:large; font-weight:bold;">Destination Address:</legend>
						<?php if(isset($this->destination_address['nice_address']) && $this->destination_address['nice_address'] != "") {echo '<p>'.$this->destination_address['nice_address'].'</p>';}?>
						<?php $destination_count = 1;
						foreach($this->destination_contacts as $contact)
						{
							if(isset($contact['nice_contact']) && $contact['nice_contact'] != "")
							{
								if($destination_count == 1)
								{
									echo '<b>Contacts:</b><br />'.$contact['nice_contact'].'<br />';
								}
								else if($destination_count != count($this->destination_contacts))
								{
									echo $contact['nice_contact'].'<br />';
								}
								else
								{
									echo $contact['nice_contact'];
								}
							}
							$destination_count++;
						}
						?>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<?php AdminUIHelper::endAdminArea(); ?>