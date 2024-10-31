<?php

/**
 * WooCommerce Routeapp Woocommerce
 *
 * @link       https://route.com/
 * @since      1.0.0
 *
 * @package    Routeapp
 * @subpackage Routeapp/includes
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

class Routeapp_Woocommerce
{

   public function __construct() {
      $this->init();
   }

   public function init() {

        if ($this->routeapp_route_enabled_extra_columns()) {
            add_filter( 'manage_edit-shop_order_columns', array( $this, 'routeapp_add_order_route_columns_header' ), 20 );
            add_action( 'manage_shop_order_posts_custom_column',  array( $this, 'routeapp_manage_route_charge_posts_custom_column' ));
            add_action( 'restrict_manage_posts', array( $this, 'routeapp_display_admin_shop_order_route_protection_filter' ) );
            add_action( 'pre_get_posts', array( $this, 'routeapp_process_admin_shop_order_route_protection' ));

            add_action( 'manage_woocommerce_page_wc-orders_custom_column',  array( $this, 'routeapp_manage_route_charge_posts_custom_column_new' ) , 99, 2 );
            add_action( 'manage_shop_order_custom_column',  array( $this, 'routeapp_manage_route_charge_posts_custom_column_new' ) , 99, 2 );

            add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'routeapp_add_order_route_columns_header' ), 20 );

        }
    }

    /**
    * Add their respective Route Protection and Route Charge columns after order date
    *
    * @since 1.0.3
    */
   public function routeapp_add_order_route_columns_header( $columns ) {
      $new_columns = array();

      foreach ( $columns as $column_name => $column_info ) {  
          $new_columns[ $column_name ] = $column_info;

          if ( 'order_date' === $column_name ) {
              $new_columns['route_protection'] = __( 'Route Protection', 'routeapp' );
              $new_columns['route_charge'] = __( 'Route Charge', 'routeapp' );
          }
      }

      return $new_columns;
   }

    /**
     * Add their respective Route Protection and Route Charge values.
     *
     * @since 1.0.3
     * @return void
     */
    public function routeapp_manage_route_charge_posts_custom_column( $column ) {
        global $post;
        $order = wc_get_order( $post->ID );

        if (  $column === 'route_charge' ) {
            echo wc_price( $this->routeapp_get_route_charge_fee( $order ), array( 'currency' => $order->get_currency() ) );
        } else if ( $column === 'route_protection' ) {
            echo $this->routeapp_order_has_route_protection_fee( $order ) ? "Yes" : "No";
        }
    }

    /**
    * Add their respective Route Protection and Route Charge values.
    *
    * @since 1.0.3
    * @return void
    */
    public function routeapp_manage_route_charge_posts_custom_column_new( $column,  $orderParam ) {
        global $post;

        // Check if $orderParam is set and not null
        if ( isset( $orderParam ) && ! is_null( $orderParam ) ) {
            $order = $orderParam; // Get the order using $orderParam if it's valid
        } else {
            $order = wc_get_order( $post->ID ); // Fallback to global $post if $orderParam is not set
        }

        if (  $column === 'route_charge' ) {
            echo wc_price( $this->routeapp_get_route_charge_fee( $order ), array( 'currency' => $order->get_currency() ) );
        } else if ( $column === 'route_protection' ) {
            echo $this->routeapp_order_has_route_protection_fee( $order ) ? "Yes" : "No";
        }
    }

    /**
    * Returns the order's post_meta and checks the Route Charge price
    *
    * @since 1.0.3
    * @param $order Order's woocommerce object
    * @return float $price
    */
   public function routeapp_get_route_charge_fee( $order ) {
       global $post;
      $price = "";
      if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
      && OrderUtil::custom_orders_table_usage_is_enabled() ) {
         // HPOS usage is enabled.
         $price = $order->get_meta('_routeapp_route_charge');
      } else {
          $orderData = wc_get_order( $post->ID );
          if ($orderData) {
              // Traditional CPT-based orders are in use.
              $price = get_post_meta($order->get_id(), '_routeapp_route_charge', true);
          }
      }
     
      
      if ( $price === "" ) {
         $price = 0;
      }

      return $price;
   }

   /**
    * Returns the order's post_meta and checks if it has Route Protection
    *
    * @since 1.0.3
    * @param $order Order's woocommerce object
    * @return bool
    */
   public function routeapp_order_has_route_protection_fee( $order ) {
       global $post;
       $route_protection = "0";
       if ( class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
         && OrderUtil::custom_orders_table_usage_is_enabled() ) {
           // HPOS usage is enabled.
           $route_protection = $order->get_meta('_routeapp_route_protection');
       } else {
           $orderData = wc_get_order( $post->ID );
           if ($orderData) {
               // Traditional CPT-based orders are in use.
               $route_protection = get_post_meta($orderData->get_id(), '_routeapp_route_protection', true);
           }
       }
       
      return $route_protection === "1";
   }

   /**
    * Create Route Protection select field with Yes and No options
    *
    * @since 1.0.3
    * @return void
    */
   public function routeapp_display_admin_shop_order_route_protection_filter() {
      global $pagenow, $post_type;

      if( 'shop_order' === $post_type && 'edit.php' === $pagenow ) {
         $options = array(
            0 => "Yes",
            1 => "No"
         );
         $current = isset($_GET['filter_shop_order_route_protection']) ? $_GET['filter_shop_order_route_protection'] : '';

         echo '<select name="filter_shop_order_route_protection"><option value="">' . __('Route Protection', 'routeapp') . '</option>';
         foreach ( $options as $option ) {
            printf('<option value="%s"%s>%s</option>', $option, 
               $option === $current ? '" selected="selected"' : '', $option );
         }
         echo '</select>';
      }
   }

   /**
    * Processes the filter that lists orders that have or do not have Route Protection..
    *
    * @since 1.0.3
    * @param $query Woocommerce Query Object
    * @return void
    */
   public function routeapp_process_admin_shop_order_route_protection( $query ) {
      global $pagenow;

      if ( $query->is_admin && $pagenow == 'edit.php' && isset( $_GET['filter_shop_order_route_protection'] ) 
        && $_GET['filter_shop_order_route_protection'] != '' && $_GET['post_type'] == 'shop_order' ) {
  
         $input = esc_attr( $_GET['filter_shop_order_route_protection'] ) === "Yes" ? 1 : 0;
         $meta_query = $query->get( 'meta_query' ) ? $query->get( 'meta_query' ) : array();

         $meta_query[] = array(
               'key' => '_routeapp_route_protection',
               'value'    => 1,
               'compare'   => intval($input) == 1 ? '==' : '!='
         );

         $query->set( 'meta_query', $meta_query );
      }
   }


   /**
    * Checks whether or not the user has selected the option to disable extra columns
    *
    * @since 1.0.3
    * @return bool
    */
   public function routeapp_route_enabled_extra_columns() {
      return get_option('routeapp_route_enable_extra_columns') !== "no";
   }
}
