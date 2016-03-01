<?php
/*
Plugin Name: Woocommerce Coupon Code
Plugin URI: http://camanoislandcoffee.com
Description: Coupon code keygen
Version: 1.0
Author: Tanner Legasse	
Author URI: 
License: GNU
*/
if (!defined('CC_URL'))
    define('CC_URL', plugins_url() . '/' . basename(dirname(__FILE__)) . '/');

if (!defined('CC_PATH'))
    define('CC_PATH', plugin_dir_path(__FILE__));

function couponMenu() {
     add_menu_page("Coupon Code Generator", "Coupon Code Generator", "manage_options", "coupon-options", 'coupon_options_callback', '/wp-content/plugins/woocommerce-coupon-generator/favicon.ico');
}
add_action('plugins_loaded', 'checkPosted');
add_action('admin_menu', 'couponMenu');

function checkPosted() {
        if (isset($_POST['cquantity'])) {
        couponGen();
    }
}

function coupon_options_callback() {
	?>
	<h1>Coupon Code Gen</h1>
	<h3>You can use this to generate coupon codes for affiliates. Wow, it sure looks fun! I hope you enjoy it!</h3>
	<form action="" method="POST">
		<input type="number" placeholder="1-10000" name="cquantity" min="1" max="10000" style="display: inline-block; max-width: 100px;"><h3 style="display: inline-block;">&nbsp Amount of coupons to generate.</h3><br>
		<input type="checkbox" name="numbers" style="display: inline-block;"><h3 style="display: inline-block;">&nbsp Include numbers.</h3><br>
		<input type="number" placeholder="1-29" name="length" min="1" max="29" style="display: inline-block; max-width: 100px;"><h3 style="display: inline-block;">&nbsp; Number of generated characters.</h3><br>
		<input type="text" placeholder="Prefix" name="prefix" style="display: inline-block; text-align: center;"><h3 style="display: inline-block;">&nbsp- Generated content goes here. -&nbsp</h3><input style="display: inline-block; text-align: center;" type="text" placeholder="Suffix" name="suffix"><br>
		<select name="discountType">
			<option value="fixed_cart">Cart dollar discount</option>
			<option value="percent">Cart percentage discount</option>
		</select>
		<input type="number" placeholder="Amount" name="discountAmount"><br>
		<input type="checkbox" name="freeShipping"><h3 style="display: inline-block;">&nbsp Free shipping as well?</h3><br>
		<input type="submit">
	</form>
<?php
}

function codeGen() {
	/* This function generates the coupon code. It adds the list of numbers to letters if the box has been ticked
	 * indicating a desire to have numbers in the coupon code. It will then shuffle the string and add prefixes
	 * and suffixes if they exist.
	 */
	$string = "ABCDEFGHJKLMNPQRSTUVWXYZ";
	$numbers = "23456789";
	if ($_POST["numbers"] == "on") {
		$string = $numbers . $string;
	}
	$shuffled = substr(str_shuffle($string) , 0 , $_POST["length"]);
	$compiled = $_POST['prefix'] . $shuffled . $_POST['suffix'];
	return $compiled;
}

function couponAdd($couponCode) {
	// The code in this function adds the coupons to the database for use in the future.
	$amount = $_POST['discountAmount']; // Amount
	$discountType = $_POST['discountType']; // Type: fixed_cart, percent, fixed_product, percent_product
	if($_POST['freeShipping'] == 'on') {
		$freeShipping = 'yes';
	} else {
		$freeShipping = 'no';
	}
	
	$coupon = array(
	    'post_title' 	=> 	$couponCode,
	    'post_content' 	=> 	'',
	    'post_status' 	=> 	'publish',
	    'post_author' 	=> 	1,
	    'post_type'		=> 	'shop_coupon');
	    
	$newCouponId = wp_insert_post( $coupon );
	update_post_meta( $newCouponId, 'discount_type', $discountType );
	update_post_meta( $newCouponId, 'coupon_amount', $amount );
	update_post_meta( $newCouponId, 'individual_use', 'yes' );
	update_post_meta( $newCouponId, 'product_ids', '' );
	update_post_meta( $newCouponId, 'exclude_product_ids', '' );
	update_post_meta( $newCouponId, 'usage_limit', '1' );
	update_post_meta( $newCouponId, 'expiry_date', '' );
	update_post_meta( $newCouponId, 'apply_before_tax', 'yes' );
	update_post_meta( $newCouponId, 'free_shipping', $freeShipping );
}

function couponGen() {
	/* The code below will look for all of the existing coupon codes in the database for comparison 
	 * with the generated coupons further down the function
	 */
		$args = array(
	    'posts_per_page'   => -1,
	    'orderby'          => 'title',
	    'order'            => 'asc',
	    'post_type'        => 'shop_coupon',
	    'post_status'      => 'publish',);

	$wooCoupons = get_posts( $args );
	$wooCouponsExisting = array();
	foreach ($wooCoupons as $wooCoupon) {
		$postTitle = $wooCoupon->post_title;
		array_push($wooCouponsExisting, $postTitle);
	}
	$coupons = array();
	$cquantity = $_POST['cquantity'];
	/* This while loop handles the coupon code generation and then checks it against a list of existing
	 * coupons and its self for the purpose of originality.
	 */
	while($cquantity > 0) {
		$compiled = codeGen();
		$i = 0;
		/* This loop checks to see if the coupon that has been generated exists in either array, and if it does, 
		 * it grabs a new one, and will try for uniqueness a max of 20 times.
		 */
		while((in_array($compiled, $coupons) OR in_array($compiled, $wooCouponsExisting)) AND $i<20) {
			$compiled = codeGen();
			$i++;
		}
		/* If the above while loop never went all the way to 20 iterations, the loop below will add it to the 
		 * list of coupons and to the table in the database. If the loop above reaches 20 iterations, it can be said 
		 * safely that the maximum number of coupons is reached and the generated code will be ignored.
		 */
		if ($i != 20) {
			// The lines below will add the coupon to the database with a function called couponAdd and add it to the internal array.
			array_push($coupons, $compiled);
		}
		$cquantity--;
	}

	header('Content-Disposition: attachment; filename=couponCodes.csv');
	header('Content-Type: text/csv; charset=utf-8');
	
	foreach ($coupons as $coupon) {
		if(in_array($coupon, $wooCouponsExisting) == FALSE) {
			couponAdd($coupon);
			echo"$coupon\r\n";
		}
	}
	exit;
}
?>