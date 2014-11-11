<?php
/* ----------------------------------------------------------------------
   $Id: block_customers_status.php,v 1.1 2007/06/07 11:55:41 r23 Exp $

   OOS [OSIS Online Shop]
   http://www.oos-shop.de/

   Copyright (c) 2003 - 2007 by the OOS Development Team.
   ----------------------------------------------------------------------
   Based on:

   File: customers_status.php,v 1.1 2003/01/08 10:53:03 elarifr 
   ----------------------------------------------------------------------
   Customers_status v3.x / Catalog part
   Copyright elari@free.fr

   Contribution based on:

   osCommerce, Open Source E-Commerce Solutions
   http://www.oscommerce.com

   Copyright (c) 2002 - 2003 osCommerce
   ----------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------- */

  /** ensure this file is being included by a parent file */
  defined( 'OOS_VALID_MOD' ) or die( 'Direct Access to this location is not allowed.' );

  $customers_status_block = 'false';

  if ($_SESSION['member']->group['public'] > 0 ) {
    $customers_status_block = 'true';
    $customers_discount = $oCurrencies->display_price($_SESSION['member']->group['ot_minimum'], '');
    $smarty->assign('customers_discount', $customers_discount);
  }

  $smarty->assign(
      array(
          'customers_status_block' => $customers_status_block,
          'block_heading_customers_status_box' => $block_heading
      )
  );

?>
