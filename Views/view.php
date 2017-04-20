<h1>Viewing Waybill: <?php echo $data->waybill; ?>
    <span style="font-size: small; padding-left: 10px;">Back to </span><a  style="font-size: small" href="<?php echo get_admin_url().'admin.php?page=mds-already-confirmed'; ?>">MDS Confirmed</a><br />
</h1>

<table>
    <tr>
        <td width="50%" style="padding-right: 15px;">
            <h3>Status Information:</h3>
            <table>
                <tr>
                    <td>
                        Waybill:
                    </td>
                    <td>
                         <a href="<?php echo get_admin_url().'admin.php?page=mds-confirmed-order-view-pdf&waybill='.$data->waybill.'&type=waybill'; ?>" rel="wrapped_waybill" class="show_waybill">View PDF</a>
                    </td>
                </tr>
                <tr>
                    <td>
                        Status:
                    </td>
                    <td>
                         <?php echo $tracking['status_text']; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        Status last updated:
                    </td>
                    <td>
                         <?php echo $tracking['updated_time'].' on the '.date('d/M/Y', strtotime($tracking['updated_date'])); ?>
                    </td>
                </tr>
                <?php if (isset($tracking['delivered_at'])):?>
                    <tr>
                        <td>
                            Delivered at:
                        </td>
                        <td>
                             <?php echo date('H:i:s', strtotime($tracking['delivered_at'])).' on the '.date('d/M/Y', strtotime($tracking['delivered_at'])); ?>
                        </td>
                    </tr>
                <?php else:?>
                    <?php if (isset($tracking['eta'])):?>
                        <tr>
                            <td>
                                Estimated time of delivery:
                            </td>
                            <td>
                                <?php echo date('H:i:s', $tracking['eta']).' on the '.date('d/M/Y', $tracking['eta']); ?>
                            </td>
                        </tr>
                    <?php else:?>
                        <tr>
                            <td>
                                Delivery will be before:
                            </td>
                            <td>
                                <?php echo $tracking['delivery_time'].' on the '.date('d/M/Y', strtotime($tracking['delivery_date'])); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>
        </td>
        <td width="50%" style="padding-left: 15px;">
            <h3>General Information:</h3>
            <table>
                <tr>
                    <td>
                        Quoted Weight: <?php echo number_format($validation_results->weight, 2, '.', ''); ?> | Actual Weight: <?php echo number_format($tracking['weight'], 2, '.', ''); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        Quoted Price: R<?php echo number_format($validation_results->price->inc_vat, 2, '.', ''); ?> | Actual Price: R<?php echo number_format($tracking['total_price'] * 1.14, 2, '.', ''); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        Risk Cover: <?php echo $validation_results->cover == 1 ? 'Yes' : 'No'; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        Proof of delivery: <?php echo '<a href="'.get_admin_url().'admin.php?page=mds-confirmed-order-view-pdf&waybill='.$data->waybill.'&type=pod" rel="wrapped_waybill" class="show_waybill">'.'View POD'.'</a>'; ?>
                    </td>
                </tr>
                <?php if (!empty($image_list)): ?>
                    <tr>
                        <td>
                            Images (<?php echo count($image_list); ?>):
                            <?php
                                $count = 1;
                                foreach ($image_list as $image) {
                                    echo ' <a href="'.$image.'" rel="image_'.$count.'" class="show_image">Image '.$count.'</a>';
                                    ++$count;
                                }
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
    <tr>
        <td width="50%" style="padding-right: 15px;">
            <h3>Collection Address:</h3>
            <?php if (isset($collection_address['nice_address']) && $collection_address['nice_address'] != ''):?>
                <p><?php echo $collection_address['nice_address']; ?></p>
            <?php endif; ?>

            <?php
                if (isset($collection_contacts)) {
                    $collection_count = 1;
                    foreach ($collection_contacts as $contact) {
                        if (isset($contact['nice_contact']) && $contact['nice_contact'] != '') {
                            if ($collection_count == 1) {
                                echo '<b>Contacts:</b><br />'.$contact['nice_contact'].'<br />';
                            } elseif ($collection_count != count($collection_contacts)) {
                                echo $contact['nice_contact'].'<br />';
                            } else {
                                echo $contact['nice_contact'];
                            }
                        }
                    }
                }
            ?>
        </td>
        <td width="50%" style="padding-left: 15px;">
            <h3>Delivery Address:</h3>
            <?php if (isset($destination_address['nice_address']) && $destination_address['nice_address'] != ''):?>
                <p><?php echo $destination_address['nice_address']; ?></p>
            <?php endif; ?>

            <?php
                if (isset($destination_contacts)) {
                    $destination_count = 1;
                    foreach ($destination_contacts as $contact) {
                        if (isset($contact['nice_contact']) && $contact['nice_contact'] != '') {
                            if ($destination_count == 1) {
                                echo '<b>Contacts:</b><br />'.$contact['nice_contact'].'<br />';
                            } elseif ($destination_count != count($destination_contacts)) {
                                echo $contact['nice_contact'].'<br />';
                            } else {
                                echo $contact['nice_contact'];
                            }
                        }
                    }
                }
            ?>
        </td>
    </tr>
</table>
