<?php
/* ----------------------------------------------------------------------

   MyOOS [Shopsystem]
   http://www.oos-shop.de/

   Copyright (c) 2003 - 2017 by the MyOOS Development Team.
   ----------------------------------------------------------------------
   Based on:

   File: customers.php,v 1.74 2003/02/06 19:28:52 project3000 
   ----------------------------------------------------------------------
   osCommerce, Open Source E-Commerce Solutions
   http://www.oscommerce.com

   Copyright (c) 2003 osCommerce

   Max Order - 2003/04/27 JOHNSON - Copyright (c) 2003 Matti Ressler - mattifinn@optusnet.com.au  
   ----------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------- */

define('OOS_VALID_MOD', 'yes');
require 'includes/main.php';

require 'includes/functions/function_customer.php';
require 'includes/functions/function_coupon.php';

require 'includes/classes/class_currencies.php';
$currencies = new currencies();

$customers_statuses_array = oos_get_customers_statuses();

$nPage = (!isset($_GET['page']) || !is_numeric($_GET['page'])) ? 1 : intval($_GET['page']); 
$action = (isset($_GET['action']) ? $_GET['action'] : '');

  if (!empty($action)) {
    switch ($action) {
       case 'setflag':
        if ( ($_GET['loginflag'] == '0') || ($_GET['loginflag'] == '1') ) {
          if (isset($_GET['cID'])) {
            oos_set_customer_login($_GET['cID'], $_GET['loginflag']);
            if ($_GET['loginflag'] == '1') {
              $customerstable = $oostable['customers'];
              $sql = "SELECT customers_firstname, customers_lastname, customers_gender, customers_email_address
                      FROM $customerstable
                      WHERE customers_id = '" . oos_db_input($_GET['cID']) . "'";
               $check_customer = $dbconn->Execute($sql);
               if ($check_customer->RecordCount()) {
                 $check_customer_values = $check_customer->fields;
                 // Crypted password mods - create a new password, update the database and mail it to them
                 $newpass = oos_create_random_value(ENTRY_PASSWORD_MIN_LENGTH);
                 $crypted_password = oos_encrypt_password($newpass);
                 $customerstable = $oostable['customers'];
                 $dbconn->Execute("UPDATE $customerstable SET customers_password = '" . $crypted_password . "' WHERE customers_id = '" . $_GET['cID'] . "'");

                 $name = $check_customer_values['customers_firstname'] . " " . $check_customer_values['customers_lastname'];
                 if (ACCOUNT_GENDER == 'true') {
                   if ($check_customer_values['customers_gender'] == 'm') {
                     $email_text = EMAIL_GREET_MR . $check_customer_values['customers_lastname'] . ',' . "\n\n";
                   } else {
                     $email_text = EMAIL_GREET_MS . $check_customer_values['customers_lastname'] . ',' . "\n\n";
                   }
                 } else {
                   $email_text = EMAIL_GREET_NONE;
                 }
                 $email_text .= EMAIL_WELCOME;
                 if (MODULE_ORDER_TOTAL_GV_STATUS == 'true') {
                   // ICW - CREDIT CLASS CODE BLOCK ADDED  BEGIN
                   if (NEW_SIGNUP_GIFT_VOUCHER_AMOUNT > 0) {
                     $coupon_code = oos_create_coupon_code();
                     $couponstable = $oostable['coupons'];
                     $insert_result = $dbconn->Execute("INSERT INTO $couponstable
                                                   (coupon_code,
                                                    coupon_type,
                                                    coupon_amount,
                                                    date_created) VALUES ('" . $coupon_code . "',
                                                                          'G',
                                                                          '" . NEW_SIGNUP_GIFT_VOUCHER_AMOUNT . "',
                                                                          now())");
                     $insert_id = $dbconn->Insert_ID();

                     $coupon_email_tracktable = $oostable['coupon_email_track'];
                     $insert_result = $dbconn->Execute("INSERT INTO $coupon_email_tracktable
                                                   (coupon_id,
                                                    customer_id_sent,
                                                    sent_firstname,
                                                    emailed_to,
                                                    date_sent) VALUES ('" . $insert_id ."',
                                                                       '0', 
                                                                       'Admin', 
                                                                       '" . $email_address . "',
                                                                       now() )"); 

                     $email_text .= sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) . "\n\n" .
                                    sprintf(EMAIL_GV_REDEEM, $coupon_code) . "\n\n" .
                                    EMAIL_GV_LINK . oos_catalog_link($oosCatalogFilename['gv_redeem'], 'gv_no=' . $coupon_code) . 
                                    "\n\n";
                   }
                   if (NEW_SIGNUP_DISCOUNT_COUPON != '') {
                     $coupon_id = NEW_SIGNUP_DISCOUNT_COUPON;

                     $couponstable = $oostable['coupons'];
                     $sql = "SELECT coupon_id coupon_type, coupon_code, coupon_amount
                             FROM $couponstable
                             WHERE coupon_id = '" . $coupon_id . "'";
                     $coupon_result = $dbconn->Execute($sql);
                     $coupon = $coupon_result->fields;

                     $coupons_descriptiontable = $oostable['coupons_description'];
                     $sql = "SELECT coupon_name, coupon_description
                             FROM $coupons_descriptiontable
                             WHERE coupon_id = '" . $coupon_id . "' AND
                                   coupon_languages_id = '" . intval($_SESSION['language_id']) . "'";
                     $coupon_desc_result = $dbconn->Execute($sql);
                     $coupon_desc = $coupon_desc_result->fields;

                     $coupon_email_tracktable = $oostable['coupon_email_track'];
                     $insert_result = $dbconn->Execute("INSERT INTO $coupon_email_tracktable
                                                       (coupon_id,
                                                        customer_id_sent,
                                                        sent_firstname,
                                                        emailed_to,
                                                        date_sent) VALUES ('" . $coupon_id ."',
                                                                           '0',
                                                                           'Admin',
                                                                           '" . $email_address . "',
                                                                           now() )");
                     $email_text .= EMAIL_COUPON_INCENTIVE_HEADER .  "\n\n" .
                                    $coupon_desc['coupon_description'] .
                                    sprintf(EMAIL_COUPON_REDEEM, $coupon['coupon_code']) . "\n\n" .
                                    "\n\n";
                   }
                 }
                 $email_text .= EMAIL_TEXT;
                 $email_text .= sprintf(EMAIL_PASSWORD_BODY, $newpass);
                 $email_text .= EMAIL_CONTACT;
                 oos_mail($name, $check_customer_values['customers_email_address'], EMAIL_SUBJECT, nl2br($email_text), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS); 
                 oos_redirect_admin(oos_href_link_admin($aContents['customers'], 'selected_box=customers&page=' . $nPage . '&cID=' . $_GET['cID']));
              }
            }
          }
        }
        break;
      case 'statusconfirm':
        $customers_id = oos_db_prepare_input($_GET['cID']);

        $customerstable = $oostable['customers'];
        $check_status_sql = "SELECT customers_status
                             FROM $customerstable
                             WHERE customers_id = '" . intval($customers_id) . "'";
        $customers_status = $dbconn->GetOne($check_status_sql);

        if ($customers_status != $pdm_status) {
          $customerstable = $oostable['customers'];
          $dbconn->Execute("UPDATE $customerstable
                            SET customers_status = '" . intval($pdm_status) . "'
                            WHERE customers_id = '" . intval($customers_id) . "'");

          $customers_status_historytable = $oostable['customers_status_history'];
          $dbconn->Execute("INSERT INTO $customers_status_historytable
                           (customers_id,
                            new_value,
                            old_value,
                            date_added,
                            customer_notified) VALUES ('" . intval($customers_id) . "',
                                                       '" . intval($pdm_status) . "',
                                                       '" . intval($customers_status) . "',
                                                       now(),
                                                       '" . $customer_notified . "')");
        }
        break;
      case 'update':
        $customers_id = oos_db_prepare_input($_GET['cID']);
        $sql_data_array = array('customers_firstname' => $customers_firstname,
                                'customers_lastname' => $customers_lastname,
                                'customers_email_address' => $customers_email_address,
                                'customers_telephone' => $customers_telephone,
                                'customers_newsletter' => $customers_newsletter,
                                'customers_max_order' => $customers_max_order);

        if (ACCOUNT_GENDER == 'true') $sql_data_array['customers_gender'] = $customers_gender;
        if (ACCOUNT_VAT_ID == 'true') {
          $sql_data_array['customers_vat_id'] = $customers_vat_id;
          $sql_data_array['customers_vat_id_status'] = $customers_vat_id_status;
        }
        if (ACCOUNT_DOB == 'true') $sql_data_array['customers_dob'] = oos_date_raw($customers_dob);

        oos_db_perform($oostable['customers'], $sql_data_array, 'UPDATE', "customers_id = '" . intval($customers_id) . "'");

        $customers_infotable = $oostable['customers_info'];
        $dbconn->Execute("UPDATE $customers_infotable SET customers_info_date_account_last_modified = now() WHERE customers_info_id = '" . intval($customers_id) . "'");

        if ($entry_zone_id > 0) $entry_state = '';

        $sql_data_array = array('entry_firstname' => $customers_firstname,
                                'entry_lastname' => $customers_lastname,
                                'entry_street_address' => $entry_street_address,
                                'entry_postcode' => $entry_postcode,
                                'entry_city' => $entry_city,
                                'entry_country_id' => $entry_country_id);

        if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = $entry_company;
        if (ACCOUNT_OWNER == 'true') $sql_data_array['entry_owner'] = $entry_owner;
        if (ACCOUNT_STATE == 'true') {
          $sql_data_array['entry_state'] = $entry_state;
          $sql_data_array['entry_zone_id'] = $entry_zone_id;
        }

        oos_db_perform($oostable['address_book'], $sql_data_array, 'UPDATE', "customers_id = '" . intval($customers_id) . "' and address_book_id = '" . oos_db_input($default_address_id) . "'");

        oos_redirect_admin(oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $customers_id));
        break;
      case 'deleteconfirm':
        $customers_id = oos_db_prepare_input($_GET['cID']);

        if (isset($_POST['delete_reviews']) && ($_POST['delete_reviews'] == 'on')) {
          $reviewstable = $oostable['reviews'];
          $reviews_result = $dbconn->Execute("SELECT reviews_id FROM $reviewstable WHERE customers_id = '" . intval($customers_id) . "'");
          while ($reviews = $reviews_result->fields) {
            $reviews_descriptiontable = $oostable['reviews_description'];
            $dbconn->Execute("DELETE FROM $reviews_descriptiontable WHERE reviews_id = '" . $reviews['reviews_id'] . "'");

            // Move that ADOdb pointer!
            $reviews_result->MoveNext();
          }
          $dbconn->Execute("DELETE FROM " . $oostable['reviews'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        } else {
          $dbconn->Execute("UPDATE " . $oostable['reviews'] . " SET customers_id = null WHERE customers_id = '" . intval($customers_id) . "'");
        }

        $dbconn->Execute("DELETE FROM " . $oostable['address_book'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['customers'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['customers_info'] . " WHERE customers_info_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['customers_basket'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['customers_basket_attributes'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['customers_wishlist'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['customers_wishlist_attributes'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['customers_status_history'] . " WHERE customers_id = '" . intval($customers_id) . "'");
        $dbconn->Execute("DELETE FROM " . $oostable['whos_online'] . " WHERE customer_id = '" . intval($customers_id) . "'");

        oos_redirect_admin(oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')))); 
        break;
    }
  }
  require 'includes/header.php';

  if ($action == 'edit') {
?>
<script language="javascript"><!--
function resetStateText(theForm) {
  theForm.entry_state.value = '';
  if (theForm.entry_zone_id.options.length > 1) {
    theForm.entry_state.value = '<?php echo JS_STATE_SELECT; ?>';
  }
}

function resetZoneSelected(theForm) {
  if (theForm.entry_state.value != '') {
    theForm.entry_zone_id.selectedIndex = '0';
    if (theForm.entry_zone_id.options.length > 1) {
      theForm.entry_state.value = '<?php echo JS_STATE_SELECT; ?>';
    }
  }
}

function update_zone(theForm) {
  var NumState = theForm.entry_zone_id.options.length;
  var SelectedCountry = '';

  while(NumState > 0) {
    NumState--;
    theForm.entry_zone_id.options[NumState] = null;
  }

  SelectedCountry = theForm.entry_country_id.options[theForm.entry_country_id.selectedIndex].value;

<?php echo oos_is_zone_list('SelectedCountry', 'theForm', 'entry_zone_id'); ?>

  resetStateText(theForm);
}

function check_form() {
  var error = 0;
  var error_message = "<?php echo JS_ERROR; ?>";

  var customers_firstname = document.customers.customers_firstname.value;
  var customers_lastname = document.customers.customers_lastname.value;
<?php 
  if (ACCOUNT_COMPANY == 'true') echo 'var entry_company = document.customers.entry_company.value;' . "\n"; 
  if (ACCOUNT_DOB == 'true') echo 'var customers_dob = document.customers.customers_dob.value;' . "\n"; 
?>
  var customers_email_address = document.customers.customers_email_address.value;  
  var entry_street_address = document.customers.entry_street_address.value;
  var entry_postcode = document.customers.entry_postcode.value;
  var entry_city = document.customers.entry_city.value;
  var customers_telephone = document.customers.customers_telephone.value;

<?php if (ACCOUNT_GENDER == 'true') { ?>
  if (document.customers.customers_gender[0].checked || document.customers.customers_gender[1].checked) {
  } else {
    error_message = error_message + "<?php echo JS_GENDER; ?>";
    error = 1;
  }
<?php } ?>

  if (customers_firstname == "" || customers_firstname.length < <?php echo ENTRY_FIRST_NAME_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_FIRST_NAME; ?>";
    error = 1;
  }

  if (customers_lastname == "" || customers_lastname.length < <?php echo ENTRY_LAST_NAME_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_LAST_NAME; ?>";
    error = 1;
  }

<?php if (ACCOUNT_DOB == 'true') { ?>
  if (customers_dob == "" || customers_dob.length < <?php echo ENTRY_DOB_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_DOB; ?>";
    error = 1;
  }
<?php } ?>

  if (customers_email_address == "" || customers_email_address.length < <?php echo ENTRY_EMAIL_ADDRESS_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_EMAIL_ADDRESS; ?>";
    error = 1;
  }

  if (entry_street_address == "" || entry_street_address.length < <?php echo ENTRY_STREET_ADDRESS_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_ADDRESS; ?>";
    error = 1;
  }

  if (entry_postcode == "" || entry_postcode.length < <?php echo ENTRY_POSTCODE_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_POST_CODE; ?>";
    error = 1;
  }

  if (entry_city == "" || entry_city.length < <?php echo ENTRY_CITY_MIN_LENGTH; ?>) {
    error_message = error_message + "<?php echo JS_CITY; ?>";
    error = 1;
  }

<?php if (ACCOUNT_STATE == 'true') { ?>
  if (document.customers.entry_zone_id.options.length <= 1) {
    if (document.customers.entry_state.value == "" || document.customers.entry_state.length < 4 ) {
       error_message = error_message + "<?php echo JS_STATE; ?>";
       error = 1;
    }
  } else {
    document.customers.entry_state.value = '';
    if (document.customers.entry_zone_id.selectedIndex == 0) {
       error_message = error_message + "<?php echo JS_ZONE; ?>";
       error = 1;
    }
  }
<?php } ?>

  if (document.customers.entry_country_id.value == 0) {
    error_message = error_message + "<?php echo JS_COUNTRY; ?>";
    error = 1;
  }


  if (error == 1) {
    alert(error_message);
    return false;
  } else {
    return true;
  }
}
//--></script>
<?php
  }
?>
<div class="wrapper">
	<!-- Header //-->
	<header class="topnavbar-wrapper">
		<!-- Top Navbar //-->
		<?php require 'includes/menue.php'; ?>
	</header>
	<!-- END Header //-->
	<aside class="aside">
		<!-- Sidebar //-->
		<div class="aside-inner">
			<?php require 'includes/blocks.php'; ?>
		</div>
		<!-- END Sidebar (left) //-->
	</aside>
	
	<!-- Main section //-->
	<section>
		<!-- Page content //-->
		<div class="content-wrapper">

<?php
  if ($action == 'edit') {
    $customerstable = $oostable['customers'];
    $address_booktable = $oostable['address_book'];
    $customers_result = $dbconn->Execute("SELECT c.customers_gender, c.customers_firstname, c.customers_lastname,
                                                 c.customers_dob, c.customers_vat_id, c.customers_vat_id_status,
                                                 c.customers_email_address, c.customers_wishlist_link_id,
                                                 a.entry_company, a.entry_owner, a.entry_street_address, a.entry_suburb,
                                                 a.entry_postcode, a.entry_city, a.entry_state, a.entry_zone_id,
                                                 a.entry_country_id, c.customers_telephone,
                                                 c.customers_newsletter, c.customers_default_address_id,
                                                 c.customers_status, c.customers_max_order
                                          FROM  $customerstable c LEFT JOIN
                                                $address_booktable a
                                            ON c.customers_default_address_id = a.address_book_id
                                         WHERE a.customers_id = c.customers_id AND
                                               c.customers_id = '" .  intval($_GET['cID']) . "'");
    $customers = $customers_result->fields;
    $cInfo = new objectInfo($customers);

    $newsletter_array = array(array('id' => '1', 'text' => ENTRY_NEWSLETTER_YES),
                              array('id' => '0', 'text' => ENTRY_NEWSLETTER_NO));

    $vat_id_status_array = array(array('id' => '1', 'text' => ENTRY_VAT_ID_STATUS_YES),
                                 array('id' => '0', 'text' => ENTRY_VAT_ID_STATUS_NO));
?>
			<!-- Breadcrumbs //-->
			<div class="row wrapper gray-bg page-heading">
				<div class="col-lg-12">
					<h2><?php echo HEADING_TITLE . ' : ' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname ; ?></h2>
					<ol class="breadcrumb">
						<li>
							<a href="<?php echo oos_href_link_admin($aContents['default']) . '">' . HEADER_TITLE_TOP . '</a>'; ?>
						</li>
						<li>
							<a href="<?php echo oos_href_link_admin($aContents['customers'], 'selected_box=customers') . '">' . BOX_HEADING_CUSTOMERS . '</a>'; ?>
						</li>
						<li class="active">
							<strong><?php echo HEADING_TITLE . ' : ' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname ; ?></strong>
						</li>
					</ol>
				</div>
			</div>
			<!-- END Breadcrumbs //-->
			
			<div class="wrapper wrapper-content">
				<div class="row">
					<div class="col-lg-12">					
<!-- body_text //-->
	<table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"></td>
            <td class="main">
<?php
   echo '<br />' . HEADING_TITLE_STATUS;
   if ($customers_statuses_array[$customers['customers_status']]['cs_image'] != '') {
      echo oos_image(OOS_SHOP_IMAGES . 'icons/' . $customers_statuses_array[$customers['customers_status']]['cs_image'], '') . ' - '; 
   }
   echo  $customers_statuses_array[$customers['customers_status']]['text'] . ' - ' . $customers_statuses_array[$customers['customers_status']]['cs_ot_discount_flag']; 
?>
            </td>
            <td class="pageHeading" align="right"></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td></td>
      </tr>
      <tr><?php echo oos_draw_form('id', 'customers', $aContents['customers'], oos_get_all_get_params(array('action')) . 'action=update', 'post', TRUE, 'onSubmit="return check_form();"') . oos_draw_hidden_field('default_address_id', $cInfo->customers_default_address_id); ?>
        <td class="formAreaTitle"><?php echo CATEGORY_PERSONAL; ?></td>
      </tr>
      <tr>
        <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
<?php
    if (ACCOUNT_GENDER == 'true') {
?>
          <tr>
            <td class="main"><?php echo ENTRY_GENDER; ?></td>
            <td class="main"><?php echo oos_draw_radio_field('customers_gender', 'm', false, $cInfo->customers_gender) . '&nbsp;&nbsp;' . MALE . '&nbsp;&nbsp;' . oos_draw_radio_field('customers_gender', 'f', false, $cInfo->customers_gender) . '&nbsp;&nbsp;' . FEMALE; ?></td>
          </tr>
<?php
    }
?>
          <tr>
            <td class="main"><?php echo ENTRY_FIRST_NAME; ?></td>
            <td class="main"><?php echo oos_draw_input_field('customers_firstname', $cInfo->customers_firstname, 'maxlength="32"', true); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_LAST_NAME; ?></td>
            <td class="main"><?php echo oos_draw_input_field('customers_lastname', $cInfo->customers_lastname, 'maxlength="32"', true); ?></td>
          </tr>
<?php
    if (ACCOUNT_DOB == 'true') {
?>
          <tr>
            <td class="main"><?php echo ENTRY_DATE_OF_BIRTH; ?></td>
            <td class="main"><?php echo oos_draw_input_field('customers_dob', oos_date_short($cInfo->customers_dob), 'maxlength="10"', true); ?></td>
          </tr>
<?php
    }
?>

          <tr>
            <td class="main"><?php echo ENTRY_EMAIL_ADDRESS; ?></td>
            <td class="main"><?php echo oos_draw_input_field('customers_email_address', $cInfo->customers_email_address, 'maxlength="96"', true); ?></td>
          </tr>
        </table></td>
      </tr>
<?php
    if (ACCOUNT_COMPANY == 'true') {
?>
      <tr>
        <td></td>
      </tr>
      <tr>
        <td class="formAreaTitle"><?php echo CATEGORY_COMPANY; ?></td>
      </tr>
      <tr>
        <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
          <tr>
            <td class="main"><?php echo ENTRY_COMPANY; ?></td>
            <td class="main"><?php echo oos_draw_input_field('entry_company', $cInfo->entry_company, 'maxlength="32"'); ?></td>
          </tr>
<?php
      if (ACCOUNT_OWNER == 'true') {
?>
          <tr>
            <td class="main"><?php echo ENTRY_OWNER; ?></td>
            <td class="main"><?php echo oos_draw_input_field('entry_owner', $cInfo->entry_owner, 'maxlength="32"'); ?></td>
          </tr>
<?php
      }
?>

<?php
      if (ACCOUNT_VAT_ID == 'true') {
?>
          <tr>
            <td class="main"><?php echo ENTRY_VAT_ID; ?></td>
            <td class="main"><?php echo oos_draw_input_field('customers_vat_id', $cInfo->customers_vat_id, 'maxlength="20"'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_VAT_ID_STATUS; ?></td>
            <td class="main"><?php echo oos_draw_pull_down_menu('customers_vat_id_status', $vat_id_status_array, $cInfo->customers_vat_id_status); ?></td>
          </tr>
<?php
      }
?>


        </table></td>
      </tr>
<?php
    }
?>

      <tr>
        <td></td>
      </tr>
      <tr>
        <td class="formAreaTitle"><?php echo CATEGORY_MAX_ORDER; ?></td>
      </tr>
      <tr>
        <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
          <tr>
            <td class="main"><?php echo ENTRY_MAX_ORDER; ?></td>
            <td class="main"><?php echo oos_draw_input_field('customers_max_order', $cInfo->customers_max_order, 'maxlength="32"'); ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
      <tr>
        <td></td>
      </tr>
      <tr>
        <td class="formAreaTitle"><?php echo CATEGORY_ADDRESS; ?></td>
      </tr>
      <tr>
        <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
          <tr>
            <td class="main"><?php echo ENTRY_STREET_ADDRESS; ?></td>
            <td class="main"><?php echo oos_draw_input_field('entry_street_address', $cInfo->entry_street_address, 'maxlength="64"', true); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_POST_CODE; ?></td>
            <td class="main"><?php echo oos_draw_input_field('entry_postcode', $cInfo->entry_postcode, 'maxlength="8"', true); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_CITY; ?></td>
            <td class="main"><?php echo oos_draw_input_field('entry_city', $cInfo->entry_city, 'maxlength="32"', true); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo ENTRY_COUNTRY; ?></td>
            <td class="main"><?php echo oos_draw_pull_down_menu('entry_country_id', oos_get_countries(), $cInfo->entry_country_id, 'onChange="update_zone(this.form);"'); ?></td>
          </tr>
<?php
    if (ACCOUNT_STATE == 'true') {
?>
          <tr>
            <td class="main"><?php echo ENTRY_STATE; ?></td>
            <td class="main"><?php echo oos_draw_pull_down_menu('entry_zone_id', oos_prepare_country_zones_pull_down($cInfo->entry_country_id), $cInfo->entry_zone_id, 'onChange="resetStateText(this.form);"'); ?></td>
          </tr>
          <tr>
            <td class="main">&nbsp;</td>
            <td class="main"><?php echo oos_draw_input_field('entry_state', $cInfo->entry_state, 'maxlength="32" onChange="resetZoneSelected(this.form);"'); ?></td>
          </tr>
<?php
    }
?>
        </table></td>
      </tr>
      <tr>
        <td></td>
      </tr>
      <tr>
        <td class="formAreaTitle"><?php echo CATEGORY_CONTACT; ?></td>
      </tr>
      <tr>
        <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
          <tr>
            <td class="main"><?php echo ENTRY_TELEPHONE_NUMBER; ?></td>
            <td class="main"><?php echo oos_draw_input_field('customers_telephone', $cInfo->customers_telephone, 'maxlength="32"', true); ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td></td>
      </tr>
      <tr>
        <td class="formAreaTitle"><?php echo CATEGORY_OPTIONS; ?></td>
      </tr>
      <tr>
        <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
          <tr>
            <td class="main"><?php echo ENTRY_NEWSLETTER; ?></td>
            <td class="main"><?php echo oos_draw_pull_down_menu('customers_newsletter', $newsletter_array, $cInfo->customers_newsletter); ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td></td>
      </tr>
      <tr>
        <td align="right" class="main"><?php echo oos_submit_button('update', IMAGE_UPDATE) . ' <a href="' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('action'))) .'">' . oos_button('cancel', BUTTON_CANCEL) . '</a>'; ?></td>
      </tr></form>
<?php
  } else {
?>
			<!-- Breadcrumbs //-->
			<div class="row wrapper gray-bg page-heading">
				<div class="col-lg-12">
					<h2><?php echo HEADING_TITLE; ?></h2>
					<ol class="breadcrumb">
						<li>
							<a href="<?php echo oos_href_link_admin($aContents['default']) . '">' . HEADER_TITLE_TOP . '</a>'; ?>
						</li>
						<li>
							<a href="<?php echo oos_href_link_admin($aContents['customers'], 'selected_box=customers') . '">' . BOX_HEADING_CUSTOMERS . '</a>'; ?>
						</li>
						<li class="active">
							<strong><?php echo HEADING_TITLE; ?></strong>
						</li>
					</ol>
				</div>
			</div>
			<!-- END Breadcrumbs //-->
			
			<div class="wrapper wrapper-content">

				<div class="row">
					<div class="col-sm-6"></div>
					<div class="col-sm-6">		
						<?php echo oos_draw_form('id', 'search', $aContents['customers'], '', 'get', FALSE, 'class="form-inline"'); ?>
							<div id="DataTables_Table_0_filter" class="dataTables_filter">		
								<label><?php echo HEADING_TITLE_SEARCH; ?></label>
								<?php echo oos_draw_input_field('search'); ?>
							</div>
						</form>
						<?php echo oos_draw_form('id', 'status', $aContents['customers'], '', 'get', FALSE, 'class="form-inline"'); ?>
							<div class="dataTables_filter">			
								<label><?php echo HEADING_TITLE_STATUS; ?></label>
								<?php echo oos_draw_pull_down_menu('status', array_merge(array(array('id' => '0', 'text' => TEXT_ALL_CUSTOMERS)), $customers_statuses_array), '0', 'onChange="this.form.submit();"'); ?>

							</div>							
						</form>				
					</div>
				</div>	  
	
				<div class="row">
					<div class="col-lg-12">			
	
	<!-- body_text //-->
	<table border="0" width="100%" cellspacing="0" cellpadding="2">  
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent">*</td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_LASTNAME; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_FIRSTNAME; ?></td>
                <td class="dataTableHeadingContent" align="left"><?php echo HEADING_TITLE_STATUS; ?></td>
                <td class="dataTableHeadingContent" align="center"><?php echo HEADING_TITLE_LOGIN; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACCOUNT_CREATED; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
    $search = '';
    if (isset($_GET['search']) && oos_is_not_null($_GET['search'])) {
      $keywords = oos_db_input(oos_db_prepare_input($_GET['search']));
      $search = "WHERE c.customers_lastname like '%" . $keywords . "%' or c.customers_firstname like '%" . $keywords . "%' or c.customers_email_address like '%" . $keywords . "'";
    }
    if (isset($_GET['status'])) {
      $status = oos_db_prepare_input($_GET['status']);
      $search ="WHERE c.customers_status = '". $status . "'";
    }

    $customerstable = $oostable['customers'];
    $address_booktable = $oostable['address_book'];
    $customers_result_raw = "SELECT c.customers_id, c.customers_lastname, c.customers_firstname, c.customers_email_address,
                                    c.customers_wishlist_link_id, c.customers_status, c.customers_login, c.customers_max_order,  
                                    a.entry_country_id, a.entry_city
                             FROM $customerstable c LEFT JOIN
                                  $address_booktable a
                               ON c.customers_id = a.customers_id AND
                                  c.customers_default_address_id = a.address_book_id
                                  " . $search . "
                             ORDER BY c.customers_lastname,
                                   c.customers_firstname";
    $customers_split = new splitPageResults($nPage, MAX_DISPLAY_SEARCH_RESULTS, $customers_result_raw, $customers_result_numrows);
    $customers_result = $dbconn->Execute($customers_result_raw);
    while ($customers = $customers_result->fields) {
      $customers_infotable = $oostable['customers_info'];
      $info_result = $dbconn->Execute("SELECT customers_info_date_account_created AS date_account_created,
                                              customers_info_date_account_last_modified AS date_account_last_modified,
                                              customers_info_date_of_last_logon AS date_last_logon,
                                              customers_info_number_of_logons AS number_of_logons
                                       FROM $customers_infotable
                                       WHERE customers_info_id = '" . $customers['customers_id'] . "'");
      $info = $info_result->fields;

      if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ($_GET['cID'] == $customers['customers_id']))) && !isset($cInfo)) {
        $countriestable = $oostable['countries'];
        $country_result = $dbconn->Execute("SELECT countries_name
                                            FROM $countriestable
                                            WHERE countries_id = '" . $customers['entry_country_id'] . "'");
        $country = $country_result->fields;

        $reviewstable = $oostable['reviews'];
        $reviews_result = $dbconn->Execute("SELECT COUNT(*) AS number_of_reviews
                                            FROM $reviewstable
                                            WHERE customers_id = '" . $customers['customers_id'] . "'");
        $reviews = $reviews_result->fields;

        $customer_info = array_merge($country, $info, $reviews);

        $cInfo_array = array_merge($customers, $customer_info);
        $cInfo = new objectInfo($cInfo_array);
      }

      if (isset($cInfo) && is_object($cInfo) && ($customers['customers_id'] == $cInfo->customers_id)) {
        echo '          <tr class="dataTableRowSelected" onmouseover="this.style.cursor=\'hand\'" onclick="document.location.href=\'' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=edit') . '\'">' . "\n";
      } else {
        echo '          <tr class="dataTableRow" onmouseover="this.className=\'dataTableRowOver\';this.style.cursor=\'hand\'" onmouseout="this.className=\'dataTableRow\'" onclick="document.location.href=\'' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID')) . 'cID=' . $customers['customers_id']) . '\'">' . "\n";
      }
?>
                <td class="dataTableContent"><?php if ($customers_statuses_array[$customers['customers_status']]['cs_image'] != '') { echo oos_image(OOS_SHOP_IMAGES . 'icons/' . $customers_statuses_array[$customers['customers_status']]['cs_image'], ''); } ?>&nbsp;</td>
                <td class="dataTableContent"><?php echo $customers['customers_lastname']; ?></td>
                <td class="dataTableContent"><?php echo $customers['customers_firstname']; ?></td>
                <td class="dataTableContent" align="left"><?php echo $customers_statuses_array[$customers['customers_status']]['text'] . '(' . $customers['customers_status'] . ')' ; ?></td>
                <td class="dataTableContent" align="center">
<?php
      if ($customers['customers_login'] == '1') {
        echo '<a href="' . oos_href_link_admin($aContents['customers'], 'selected_box=customers&page=' . $nPage . '&action=setflag&loginflag=0&cID=' . $customers['customers_id']) . '">' . oos_image(OOS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
      } else {
        echo '<a href="' . oos_href_link_admin($aContents['customers'], 'selected_box=customers&page=' . $nPage . '&action=setflag&loginflag=1&cID=' . $customers['customers_id']) . '">' . oos_image(OOS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>';
      }
?>
                <td class="dataTableContent" align="right"><?php echo oos_date_short($info['date_account_created']); ?></td>
                <td class="dataTableContent" align="right"><?php if (isset($cInfo) && is_object($cInfo) && ($customers['customers_id'] == $cInfo->customers_id) ) { echo '<button class="btn btn-info" type="button"><i class="fa fa-check"></i></button>'; } else { echo '<a href="' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID')) . 'cID=' . $customers['customers_id']) . '"><button class="btn btn-default" type="button"><i class="fa fa-eye-slash"></i></button></a>'; } ?>&nbsp;</td>
              </tr>
<?php
      // Move that ADOdb pointer!
      $customers_result->MoveNext();
    }

    // Close result set
    $customers_result->Close();

?>
              <tr>
                <td colspan="7"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $customers_split->display_count($customers_result_numrows, MAX_DISPLAY_SEARCH_RESULTS, $nPage, TEXT_DISPLAY_NUMBER_OF_CUSTOMERS); ?></td>
                    <td class="smallText" align="right"><?php echo $customers_split->display_links($customers_result_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $nPage, oos_get_all_get_params(array('page', 'info', 'x', 'y', 'cID'))); ?></td>
                  </tr>
<?php
    if (oos_is_not_null($_GET['search'])) {
?>
                  <tr>
                    <td align="right" colspan="7"><?php echo '<a href="' . oos_href_link_admin($aContents['customers']) . '">' . oos_button('reset', IMAGE_RESET) . '</a>'; ?></td>
                  </tr>
<?php
    }
?>
                </table></td>
              </tr>
            </table></td>
<?php
  $heading = array();
  $contents = array();

  switch ($action) {
    case 'confirm':
      $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_DELETE_CUSTOMER . '</b>');

      $contents = array('form' => oos_draw_form('id', 'customers', $aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=deleteconfirm', 'post', FALSE));
      $contents[] = array('text' => TEXT_DELETE_INTRO . '<br /><br /><b>' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</b>');
      if ($cInfo->number_of_reviews > 0) $contents[] = array('text' => '<br />' . oos_draw_checkbox_field('delete_reviews', 'on', true) . ' ' . sprintf(TEXT_DELETE_REVIEWS, $cInfo->number_of_reviews));
      $contents[] = array('align' => 'center', 'text' => '<br />' . oos_submit_button('delete', BUTTON_DELETE) . ' <a href="' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id) . '">' . oos_button('cancel', BUTTON_CANCEL) . '</a>');
      break;

    case 'editstatus':
      $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_STATUS_CUSTOMER . '</b>');
      $contents = array('form' => oos_draw_form('id', 'customers', $aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=statusconfirm', 'post', FALSE));
      $contents[] = array('text' => '<br />' . oos_draw_pull_down_menu('pdm_status', array_merge(array(array('id' => '0', 'text' => PULL_DOWN_DEFAULT)), $customers_statuses_array), $cInfo->customers_status) );
      $contents[] = array('text' => '<table border="0" cellspacing="0" cellpadding="5"><tr><td class="smallText" align="center">' . TABLE_HEADING_NEW_VALUE .' </td><td class="smallText" align="center">' . TABLE_HEADING_DATE_ADDED . '</td></tr>');

      $customers_status_historytable = $oostable['customers_status_history'];
      $customers_history_sql = "SELECT new_value, old_value, date_added, customer_notified 
                                FROM $customers_status_historytable
                                WHERE customers_id = '" . oos_db_input($cID) . "'
                                ORDER BY customers_status_history_id DESC";
      $customers_history_result = $dbconn->Execute($customers_history_sql);
      if ($customers_history_result->RecordCount()) {
        while ($customers_history = $customers_history_result->fields) {
          $contents[] = array('text' => '<tr>' . "\n" . '<td class="smallText">' . $customers_statuses_array[$customers_history['new_value']]['text'] . '</td>' . "\n" .'<td class="smallText" align="center">' . oos_datetime_short($customers_history['date_added']) . '</td>' . "\n" .'<td class="smallText" align="center">');
          $contents[] = array('text' => '</tr>' . "\n");

          // Move that ADOdb pointer!
          $customers_history_result->MoveNext();
        }
      } else {
          $contents[] = array('text' => '<tr>' . "\n" . ' <td class="smallText" colspan="2">' . TEXT_NO_CUSTOMER_HISTORY . '</td>' . "\n" . ' </tr>' . "\n");
      }
      $contents[] = array('text' => '</table>');
      $contents[] = array('align' => 'center', 'text' => '<br />' . oos_submit_button('update', IMAGE_UPDATE) . ' <a href="' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id) . '">' . oos_button('cancel', BUTTON_CANCEL) . '</a>');
      break;

    default:
      $customer_status = oos_get_customers_status ($cID);
      $cs_id           = $customer_status['customers_status'];
      $cs_name         = $customer_status['customers_status_name'];
      $cs_image        = $customer_status['customers_status_image'];
      $cs_ot_discount_flag  = $customer_status['customers_status_ot_discount_flag'];
      $cs_ot_discount       = $customer_status['customers_status_ot_discount'];
      $cs_qty_discounts     = $customer_status['customers_status_qty_discounts'];
      $cs_payment = $customer_status['customers_status_payment'];

      if (isset($cInfo) && is_object($cInfo)) {
        $heading[] = array('text' => '<b>' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname . '</b>');

        $contents[] = array('align' => 'center', 'text' => '<a href="' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=edit') . '">' . oos_button('edit', BUTTON_EDIT) . '</a> <a href="' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=confirm') . '">' . oos_button('delete',  BUTTON_DELETE) . '</a> <a href="' . oos_href_link_admin($aContents['orders'], 'cID=' . $cInfo->customers_id) . '">' . oos_button('orders', IMAGE_ORDERS) . '</a> <a href="' . oos_href_link_admin($aContents['mail'], 'selected_box=tools&customer=' . $cInfo->customers_email_address) . '">' . oos_button('email', IMAGE_EMAIL) . '</a>');
        $contents[] = array('align' => 'center', 'text' => '<a href="' . oos_catalog_link($oosCatalogFilename['wishlist'],  'wlid=' . $cInfo->customers_wishlist_link_id) . '">' . oos_button('wishlist', IMAGE_WISHLIST) . '</a> <a href="' . oos_href_link_admin($aContents['customers'], oos_get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=editstatus') . '">' . oos_button('status', IMAGE_STATUS) . '</a>');       

        $manual_infotable = $oostable['manual_info'];
        $sql = "SELECT man_info_id, man_key, status
                FROM $manual_infotable
                WHERE man_info_id = '1'";
        $login_result = $dbconn->Execute($sql);
        $login = $login_result->fields;
        if ($login['status'] != '0') {
          $contents[] = array('align' => 'center', 'text' => oos_draw_login_form('login',$oosCatalogFilename['login_admin'], 'action=login_admin','POST', 'target=_blank') . oos_draw_hidden_field('verif_key', $login['man_key']) . oos_draw_hidden_field('email_address', $cInfo->customers_email_address) . oos_submit_button('login', IMAGE_LOGIN) . '</form>');
        }
        $contents[] = array('text' => '<br />'  . oos_customers_payment($customer_status['customers_status_payment']));
        $contents[] = array('text' => '<br />' . TEXT_DATE_ACCOUNT_CREATED . ' ' . oos_date_short($cInfo->date_account_created));
        $contents[] = array('text' => '<br />' . TEXT_DATE_ACCOUNT_LAST_MODIFIED . ' ' . oos_date_short($cInfo->date_account_last_modified));
        $contents[] = array('text' => '<br />' . TEXT_INFO_DATE_LAST_LOGON . ' '  . oos_date_short($cInfo->date_last_logon));
        $contents[] = array('text' => '<br />' . TEXT_INFO_NUMBER_OF_LOGONS . ' ' . $cInfo->number_of_logons);
        $contents[] = array('text' => '<br />' . TEXT_INFO_COUNTRY . ' ' . $cInfo->countries_name);
        $contents[] = array('text' => '<br />' . TEXT_INFO_NUMBER_OF_REVIEWS . ' ' . $cInfo->number_of_reviews);
      }
      break;
  }

  if ( (oos_is_not_null($heading)) && (oos_is_not_null($contents)) ) {
    echo '            <td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox($heading, $contents);

    echo '            </td>' . "\n";
  }
?>
          </tr>
        </table></td>
      </tr>
<?php
  }
?>
    </table>
<!-- body_text_eof //-->

				</div>
			</div>
        </div>

		</div>
	</section>
	<!-- Page footer //-->
	<footer>
		<span>&copy; 2017 - <a href="http://www.oos-shop.de/" target="_blank">MyOOS [Shopsystem]</a></span>
	</footer>
</div>


<?php 
	require 'includes/bottom.php';
	require 'includes/nice_exit.php';
?>
