<form method="post" action="">
    <div id="header">
        <div id="filterbox">
            <table>
                <tr>
                    <td align="left" width="100%">
                        <label for="waybill">Filter Waybill:</label>
                        <input type="text" name="waybill" size="11"/>
                        <label for="status"> Filter Status:</label>
                        ( Open <input type="radio" name="status" checked="checked" value="1"/> | Closed <input type="radio" name="status" value="0"/> )
                        <input type="submit" value="Search"/>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <div class="datagrid">
        <table class="adminlist" cellspacing="0" cellpadding="0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Waybill Number</th>
                <th>Cust Ref</th>
                <th>Shipping Method</th>
                <th>Order Date</th>
                <th>Special Instructions</th>
                <th>MDS Rate Ex Vat</th>
            </tr>

            </thead>
            <tbody>
            <?php if (count($colliveries) > 0):
                $count = 0;
                foreach ($colliveries as $key => $order):
                    $validation_results = json_decode($order->validation_results);
                    ++$count;
                    ?>
                    <tr <?php if ($count % 2 == 0) {
                        echo ' class="alt" ';
                    } ?>>
                        <td><?php echo $order->get_id(); ?></td>
                        <td><a href="<?php echo get_admin_url().'admin.php?page=mds_confirmed&waybill='.$order->waybill; ?>"><?php echo $order->waybill; ?></a></td>
                        <td><?php echo $validation_results->cust_ref; ?></td>
                        <td><?php echo $services[$validation_results->service]; ?></td>
                        <td><?php echo date('Y-m-d H:m', $validation_results->collection_time); ?></td>
                        <td><?php echo $validation_results->instructions; ?></td>
                        <td><?php echo "R ".number_format($validation_results->price->ex_vat,2); ?></td>
                    </tr>
                <?php
                endforeach;
            endif;
            ?>
            </tbody>
        </table>
    </div>
</form>
