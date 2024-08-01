<?php

/***************************************************************************
*                                                                          *
*   ( c ) 2017 Newsman                                                       *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
*
****************************************************************************/

if ( !defined( 'BOOTSTRAP' ) ) {
    die( 'Access denied' );
}

use Tygh\Registry;
use Tygh\Settings;

require_once( realpath( dirname( __FILE__ ) ) . '/lib/Newsman/Client.php' );

if ( $_SERVER[ 'REQUEST_METHOD' ] == 'GET' && !empty( $_GET[ 'addon' ] ) && $_GET[ 'addon' ] == 'newsman' ) {
    $data = array();
    isOauth( $data );
}

if ( $_SERVER[ 'REQUEST_METHOD' ] == 'POST' ) {

    //Execute if on settings page
    if ( !empty( $_POST[ 'selected_section' ] ) && $_POST[ 'selected_section' ] == 'newsman_general' ) {

        $batchSize = 5000;

        $vars = Registry::get( 'addons.newsman' );
        $userid = $vars[ 'newsman_userid' ];
        $apikey = $vars[ 'newsman_apikey' ];
        $listid = $vars[ 'newsman_list' ];
        $segmentid = $vars[ 'newsman_segment' ];

        $segmentidPost = 0;
        $listidPost = 0;
        $opts = $_POST[ 'addon_data' ][ 'options' ];
        $c = 0;

        foreach ( $opts as $key => $val ) {
            $c++;
            if ( $c == 4 ) {
                $listidPost = $opts[ $key ];
            }
            if ( $c == 5 ) {
                $segmentidPost = $opts[ $key ];
                break;
            }
        }

        $importType = $vars[ 'newsman_importType' ];

        if ( empty( $userid ) || empty( $apikey ) ) {
            fn_set_notification( 'W', 'User Id / Api Key', 'Insert User Id or Api Key, or use OAuth', 'S' );
            return false;
        }

        if ( !empty( $userid ) || !empty( $apikey ) ) {
            try {
                $client = new Newsman_Client( $userid, $apikey );
                $lists = $client->list->all();
            } catch ( Exception $ex ) {
                fn_set_notification( 'W', 'Credentials', 'User Id and Api Key are invalid, Save again to take effect, Error: ' . $ex->getMessage(), 'S' );
                return false;
            }
        }

        if ( $segmentidPost == 0 ) {
            $segmentidPost = array();
        } else {
            $segmentidPost = array( $segmentidPost );
        }

        if ( $listidPost == 0 ) {
            fn_set_notification( 'W', 'Check fields', 'Select a list', 'S' );
            return false;
        }

        if ( !isset( $importType[ 'importOrders' ] ) && !isset( $importType[ 'importSubscribers' ] ) ) {
            fn_set_notification( 'W', 'Import Type', 'Please choose an import type (Subscribers or Customers), Save again to take effect', 'S' );
            return false;
        }

        if ( empty( $listid ) ) {
            fn_set_notification( 'W', 'List', 'List empty, save again to take effect', 'S' );
            return false;
        }

        $vars = Registry::get( 'addons.newsman' );
        $userid = $vars[ 'newsman_userid' ];
        $apikey = $vars[ 'newsman_apikey' ];
        $listid = $vars[ 'newsman_list' ];

        $client = new Newsman_Client( $userid, $apikey );

        $url = 'https://' . getenv( 'HTTP_HOST' ) . '/index.php?dispatch=newsman.view&newsman=products.json&nzmhash=' .  $apikey;

        try {
            $ret = $client->feeds->setFeedOnList( $listidPost, $url, getenv( 'HTTP_HOST' ), 'NewsMAN' );

        } catch ( Exception $ex ) {
            //cannot update identical FEED
        }

        return;

    }

}

function isOauth( &$data, $checkOnlyIsOauth = false ) {
    $redirUri = urlencode( 'https://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ] );
    $redirUri = str_replace( 'amp%3B', '', $redirUri );
    $data[ 'oauthUrl' ] = 'https://newsman.app/admin/oauth/authorize?response_type=code&client_id=nzmplugin&nzmplugin=Cscart&scope=api&redirect_uri=' . $redirUri;

    // OAuth processing
    $error = '';
    $dataLists = array();
    $data[ 'oauthStep' ] = 1;
    $viewState = array();

    if ( !empty( $_GET[ 'error' ] ) ) {
        switch ( $error ) {
            case 'access_denied':
            $error = 'Access is denied';
            break;
            case 'missing_lists':
            $error = 'There are no lists in your NewsMAN account';
            break;
        }
    } elseif ( !empty( $_GET[ 'code' ] ) ) {
        $authUrl = 'https://newsman.app/admin/oauth/token';

        $code = $_GET[ 'code' ];

        $redirect = 'https://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];

        $body = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => 'nzmplugin',
            'redirect_uri' => $redirect
        );

        $ch = curl_init( $authUrl );

        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $body ) );

        $response = curl_exec( $ch );

        if ( curl_errno( $ch ) ) {
            $error .= 'cURL error: ' . curl_error( $ch );
        }

        curl_close( $ch );

        if ( $response !== false ) {
            $response = json_decode( $response );

            $data[ 'creds' ] = json_encode( array(
                'newsman_userid' => $response->user_id,
                'newsman_apikey' => $response->access_token
            ) );

            $data[ 'newsman_userid' ] = $response->user_id;
            $data[ 'newsman_apikey' ] = $response->access_token;

        } else {
            $error .= 'Error sending cURL request.';
        }
    }

    $vars = Registry::get( 'addons.newsman' );
    $_apikey = $vars[ 'newsman_apikey' ];

    $data[ 'isOauth' ] = empty( $_apikey );

    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.getElementById('container_addon_option_newsman_newsman_button_placeholder');
        if (container) {
            // Remove all existing content
            container.innerHTML = '';

            // Create new anchor element
            var anchor = document.createElement('a');
            anchor.href = '" . $data[ 'oauthUrl' ] . "';
            anchor.id = 'newsman_action_button';
            anchor.className = 'btn btn-primary';
            anchor.target = '_blank';
            anchor.innerText = 'Newsman OAuth';

            // Append the anchor to the container
            container.appendChild(anchor);
        }

        var showButtonSection = " . ( $data[ 'isOauth' ] ? 'true' : 'false' ) . ";

        if (showButtonSection) {
            document.getElementById('newsman_action_button').style.display = 'block';
        } else {
            document.getElementById('newsman_action_button').style.display = 'none';
        }

        // Set the values for API key and User ID
        var userIdField = document.getElementById('addon_option_newsman_newsman_userid');
        var apiKeyField = document.getElementById('addon_option_newsman_newsman_apikey');
        " . ( !empty( $_GET[ 'code' ] ) ? "
        if (userIdField && apiKeyField) {
            userIdField.value = '" . $data[ 'newsman_userid' ] . "';
            apiKeyField.value = '" . $data[ 'newsman_apikey' ] . "';
        }
        " : '' ) . "
    });
    </script>";
}

function safeForCsv( $str ) {
    return '"' . str_replace( '"', '""', $str ) . '"';
}

function _importData( &$data, $list, $segments = null, $client, $source ) {
    $csv = '"email","fullname","source"' . PHP_EOL;

    foreach ( $data as $_dat ) {
        $csv .= sprintf(
            '%s,%s,%s',
            safeForCsv( $_dat[ 'email' ] ),
            safeForCsv( $_dat[ 'name' ] ),
            safeForCsv( $source )
        );
        $csv .= PHP_EOL;
    }

    $ret = null;
    try {
        if ( is_array( $segments ) && count( $segments ) > 0 ) {
            $ret = $client->import->csv( $list, $segments, $csv );
        } else {
            $ret = $client->import->csv( $list, array(), $csv );
        }

        if ( $ret == '' ) {
            throw new Exception( 'Import failed' );
        }
    } catch ( Exception $e ) {

    }

    $data = array();
}

function _importDataOrders( &$data, $list, $segments = null, $client, $source ) {
    $csv = '"email","firstname", "lastname", "city", "source"' . PHP_EOL;

    foreach ( $data as $_dat ) {
        $csv .= sprintf(
            '%s,%s,%s,%s,%s',
            safeForCsv( $_dat[ 'email' ] ),
            safeForCsv( $_dat[ 's_firstname' ] ),
            safeForCsv( $_dat[ 's_lastname' ] ),
            safeForCsv( $_dat[ 's_city' ] ),
            safeForCsv( $source )
        );
        $csv .= PHP_EOL;
    }

    $ret = null;
    try {
        if ( is_array( $segments ) && count( $segments ) > 0 ) {
            $ret = $client->import->csv( $list, $segments, $csv );
        } else {
            $ret = $client->import->csv( $list, array(), $csv );
        }

        if ( $ret == '' ) {
            throw new Exception( 'Import failed' );
        }
    } catch ( Exception $e ) {

    }

    $data = array();
}

function cronTime( $action, $value = null ) {
    $val = '';

    switch ( $action ) {
        case 'Insert':
        $val = db_query( 'INSERT INTO ?:newsman_credentials (`time`) VALUES (' . $value . ');' );
        break;
        case 'Update':
        $val = db_query( 'UPDATE ?:newsman_credentials SET `time` = ' . $value . ';' );
        break;
        case 'Select':
        $val = db_query( 'SELECT `time` FROM ?:newsman_credentials' );
        break;
    }

    return $val;
}

function fn_settings_variants_addons_newsman_newsman_segment() {
    try {
        $vars = Registry::get( 'addons.newsman' );
        $userid = $vars[ 'newsman_userid' ];
        $apikey = $vars[ 'newsman_apikey' ];
        $listid = $vars[ 'newsman_list' ];
        if ( !empty( $userid ) || !empty( $apikey ) ) {
            $client = new Newsman_Client( $userid, $apikey );
            $segments = $client->segment->all( $listid );
        }

        $all_datas = array();

        if ( !empty( $segments ) ) {
            foreach ( $segments as $segment ) {
                $all_datas[ $segment[ 'segment_id' ] ] = $segment[ 'segment_name' ];
            }
        } else {
            $all_datas[ '0' ] = 'Select and update list';
        }

    } catch ( Exception $ex ) {

        return false;
    }

    return $all_datas;
}

function getStores() {
    $stores = db_query( 'SELECT * FROM ?:companies WHERE status = ?i', 'A' );

    $_stores = array();

    foreach ( $stores as $s ) {
        $_stores[] = array(
            'storefront' => $s[ 'storefront' ]
        );
    }

    return $_stores;
}

function fn_newsman_newsman_info() {

    $vars = Registry::get( 'addons.newsman' );
    $apikey = $vars[ 'newsman_apikey' ];

    $html = "<p>Cron Sync url (setup on task scheduler / hosting) - Subscribers:<br> <a target='_blank' href='https://" . getenv( 'HTTP_HOST' ) . '/index.php?dispatch=newsman.view&cron=true&apikey=' . $apikey . "&newsman=subscribers'>https://" . getenv( 'HTTP_HOST' ) . '/index.php?dispatch=newsman.view&cron=true&apikey=' . $apikey . '&newsman=subscribers&limit=9999</a></p>';

    $html .= "<p>Cron Sync url (setup on task scheduler / hosting) - Customers with orders completed:<br> <a target='_blank' href='https://" . getenv( 'HTTP_HOST' ) . '/index.php?dispatch=newsman.view&cron=true&apikey=' . $apikey . "&newsman=orders'>https://" . getenv( 'HTTP_HOST' ) . '/index.php?dispatch=newsman.view&cron=true&apikey=' . $apikey . '&newsman=orders&limit=9999</a></p>';

    return $html;
}

function fn_settings_variants_addons_newsman_newsman_remarketingenable() {
    $all_datas = array();
    $all_datas[ '0' ] = 'No';
    $all_datas[ '1' ] = 'Yes';

    return $all_datas;
}

function fn_settings_variants_addons_newsman_newsman_remarketinglblone() {
    try {
        $stores = getStores();

        $all_datas = array();

        if ( !empty( $stores ) ) {
            foreach ( $stores as $s ) {
                $s[ 'storefront' ] = preg_replace( '/^www\./', '', $s[ 'storefront' ] );

                $all_datas[ $s[ 'storefront' ] ] = $s[ 'storefront' ];
            }
        } else {
            $all_datas[ '0' ] = 'No stores';
        }

    } catch ( Exception $ex ) {

        return false;
    }

    return $all_datas;
}

function fn_settings_variants_addons_newsman_newsman_remarketinglbltwo() {
    try {
        $stores = getStores();

        $all_datas = array();

        if ( count( $stores ) < 2 )
        return '';

        if ( !empty( $stores ) ) {
            foreach ( $stores as $s ) {
                $s[ 'storefront' ] = preg_replace( '/^www\./', '', $s[ 'storefront' ] );

                $all_datas[ $s[ 'storefront' ] ] = $s[ 'storefront' ];
            }
        } else {

            $all_datas[ '0' ] = 'No stores';
        }

    } catch ( Exception $ex ) {

        return false;
    }

    return $all_datas;
}

function fn_settings_variants_addons_newsman_newsman_remarketinglblthree() {
    try {
        $stores = getStores();

        $all_datas = array();

        if ( count( $stores ) < 3 )
        return '';

        if ( !empty( $stores ) ) {
            foreach ( $stores as $s ) {
                $s[ 'storefront' ] = preg_replace( '/^www\./', '', $s[ 'storefront' ] );

                $all_datas[ $s[ 'storefront' ] ] = $s[ 'storefront' ];
            }
        } else {
            $all_datas[ '0' ] = 'No stores';
        }

    } catch ( Exception $ex ) {

        return false;
    }

    return $all_datas;
}

function fn_settings_variants_addons_newsman_newsman_remarketinglblfour() {
    try {
        $stores = getStores();

        $all_datas = array();

        if ( count( $stores ) < 4 )
        return '';

        if ( !empty( $stores ) ) {
            foreach ( $stores as $s ) {
                $s[ 'storefront' ] = preg_replace( '/^www\./', '', $s[ 'storefront' ] );

                $all_datas[ $s[ 'storefront' ] ] = $s[ 'storefront' ];
            }
        } else {
            $all_datas[ '0' ] = 'No stores';
        }

    } catch ( Exception $ex ) {

        return false;
    }

    return $all_datas;
}

function fn_settings_variants_addons_newsman_newsman_remarketinglblfive() {
    try {
        $stores = getStores();

        $all_datas = array();

        if ( count( $stores ) < 5 )
        return '';

        if ( !empty( $stores ) ) {
            foreach ( $stores as $s ) {
                $s[ 'storefront' ] = preg_replace( '/^www\./', '', $s[ 'storefront' ] );

                $all_datas[ $s[ 'storefront' ] ] = $s[ 'storefront' ];
            }
        } else {
            $all_datas[ '0' ] = 'No stores';
        }

    } catch ( Exception $ex ) {

        return false;
    }

    return $all_datas;
}

function fn_settings_variants_addons_newsman_newsman_list() {
    try {
        $vars = Registry::get( 'addons.newsman' );
        $userid = $vars[ 'newsman_userid' ];
        $apikey = $vars[ 'newsman_apikey' ];

        if ( !empty( $userid ) || !empty( $apikey ) ) {
            $client = new Newsman_Client( $userid, $apikey );
            $lists = $client->list->all();
        }

        $all_datas = array();

        if ( !empty( $lists ) ) {
            foreach ( $lists as $list ) {
                $all_datas[ $list[ 'list_id' ] ] = $list[ 'list_name' ];
            }
        } else {
            $all_datas[ '0' ] = 'Insert UserId and ApiKey';
        }

    } catch ( Exception $ex ) {
        fn_set_notification( 'W', 'Credentials', 'Error: ' . $ex->getMessage(), 'S' );
        return false;
    }

    return $all_datas;
}

function fn_newsman_update_user_profile_post( $user_id, $user_data, $action ) {

}

?>