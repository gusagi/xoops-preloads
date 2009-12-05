<?php
/**
 * SSL filter for XOOPS Cube Legacy
 *
 * @author  Makoto Hashiguchi a.k.a. gusagi<gusagi@gusagi.com>
 * @link http://www.gusagi.com/
 * @version 1.0
 * @package xoops-preloads
 * @copyright 2009 Makoto Hashiguchi
 * @license http://www.opensource.org/licenses/bsd-license.php The BSD License
 */

if( ! defined( 'XOOPS_ROOT_PATH' ) ) exit ;

if ( ! class_exists('SslFilter') ) {
    class SslFilter extends XCube_ActionFilter
    {
        function postFilter()
        {
            /**
             * 携帯からのアクセスでなければフィルターを有効にする
             */
            if (class_exists('WizMobile')) {
                $user =& Wizin_User::getSingleton();
                if ($user->bIsMobile == false) {
                    ob_start(array($this, '_sslFilter'));
                }
            } else if (defined('HYP_K_TAI_RENDER') === true) {
                if (HYP_K_TAI_RENDER === false) {
                    ob_start(array($this, '_sslFilter'));
                }
            } else {
                ob_start(array($this, '_sslFilter'));
            }
        }

        function _sslFilter($buf = '')
        {
            /**
             * SSL対応させたいモジュールを設定
             */
            $modules = array('pm', 'inquiry', 'message');

            /**
             * SSL時のXOOPS_URL(httpがhttpsに変わるだけなら不要)
             */
            //$sslXoopsUrl = 'https://example.com';

            /**
             * SSL変換用の正規表現を生成
             */
            if (isset($sslXoopsUrl) === false || $sslXoopsUrl === '') {
                $sslXoopsUrl = str_replace('http://', 'https://', XOOPS_URL);
            }
            $sslModuleRegex = '';
            foreach ($modules as $module) {
            	$sslModuleRegex .= '|\/modules\/' .$module .'\/';
            }
            $replaceLinkPattern = '(' .strtr(XOOPS_URL, array('/' => '\/', '.' => '\.')) .')(' .
                '\/user\.php|\/register\.php|\/edituser\.php|\/lostpass\.php|\/userinfo\.php' .
                $sslModuleRegex .')(\S*)';

            /**
             * リンクの書き換え
             */
            $pattern = '(<a)([^>]*)(href=)([\"\'])' . $replaceLinkPattern . '([\"\'])([^>]*)(>)';
            preg_match_all("/" .$pattern ."/i", $buf, $matches, PREG_SET_ORDER);
            if ( ! empty($matches) ) {
                foreach ( $matches as $key => $match) {
                    $link = str_replace($match[5], $sslXoopsUrl, $match[0]);
                    $buf = str_replace($match[0], $link, $buf);
                }
            }

            /**
             * フォームの書き換え
             */
            $pattern = '(<form)([^>]*)(action=)([\"\'])' . $replaceLinkPattern . '([\"\'])([^>]*)(>)';
            preg_match_all( "/" .$pattern ."/i", $buf, $matches, PREG_SET_ORDER );
            if ( ! empty($matches) ) {
                foreach ($matches as $key => $match) {
                    if (! empty($match[5])) {
                        $form = $match[0];
                        $action = $match[5];
                        if (substr($action, 0, 5) === 'http:') {
                            $form = str_replace($match[5], $sslXoopsUrl, $match[0]);
                            $buf = str_replace($match[0], $form, $buf);
                        }
                        $action = '';
                    }
                }
            }

            /**
             * 現在がSSLアクセス中なら、ページ内の画像パスなどを書き換える
             */
            if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'] === 'on')) {
                $replaceLinkPattern = '(' . strtr(XOOPS_URL, array('/' => '\/', '.' => '\.')) . ')(\S*)';

                /**
                 * Google Analyticsの書き換え(古いAnalyticsコード用)
                 */
                $buf = str_replace('http://www.google-analytics.com/urchin.js',
                    'https://ssl.google-analytics.com/urchin.js', $buf);

                /**
                 * 画像パスの書き換え
                 */
                $pattern = '(<img)([^>]*)(src=)([\"\'])' . $replaceLinkPattern . '([\"\'])([^>]*)(>)';
                preg_match_all( "/" .$pattern ."/i", $buf, $matches, PREG_SET_ORDER );
                if (! empty($matches)) {
                    foreach ($matches as $key => $match) {
                        $link = str_replace($match[5], $sslXoopsUrl, $match[0]);
                        $buf = str_replace($match[0], $link, $buf);
                    }
                }

                /**
                 * CSSパスの書き換え
                 */
                $pattern = '(<link)([^>]*)(href=)([\"\'])' . $replaceLinkPattern . '([\"\'])([^>]*)(>)';
                preg_match_all( "/" .$pattern ."/i", $buf, $matches, PREG_SET_ORDER );
                if (! empty($matches)) {
                    foreach ($matches as $key => $match) {
                        $link = str_replace($match[5], $sslXoopsUrl, $match[0]);
                        $buf = str_replace($match[0], $link, $buf);
                    }
                }

                /**
                 * JavaScriptパスの書き換え
                 */
                $pattern = '(<script)([^>]*)(src=)([\"\'])' . $replaceLinkPattern . '([\"\'])([^>]*)(>)';
                preg_match_all( "/" .$pattern ."/i", $buf, $matches, PREG_SET_ORDER );
                if (! empty($matches)) {
                    foreach ($matches as $key => $match) {
                        $link = str_replace($match[5], $sslXoopsUrl, $match[0]);
                        $buf = str_replace($match[0], $link, $buf);
                    }
                }

                /**
                 * テーマ関連のファイルパス書き換え
                 */
                $context =& $this->mRoot->getContext();
                $themeName = $context->getThemeName();
                $themeUrl = XOOPS_THEME_URL .'/' .$themeName .'/';
                $sslThemeUrl = str_replace(XOOPS_URL, $sslXoopsUrl, $themeUrl);
                $pattern = '(<)([^>]*)(=)([\"\'])(' .
                    strtr($themeUrl, array('/' => '\/', '.' => '\.')) .
                    ')([^>]*?)([\"\'])([^>]*)(>)';
                preg_match_all("/" .$pattern ."/i", $buf, $matches, PREG_SET_ORDER);
                if (! empty($matches)) {
                    foreach ($matches as $key => $match) {
                        $link = str_replace($match[5], $sslThemeUrl, $match[0]);
                        $buf = str_replace($match[0], $link, $buf);
                    }
                }
            }
            return $buf;
        }
    }
}

