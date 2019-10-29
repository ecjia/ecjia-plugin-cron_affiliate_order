<?php
//
//    ______         ______           __         __         ______
//   /\  ___\       /\  ___\         /\_\       /\_\       /\  __ \
//   \/\  __\       \/\ \____        \/\_\      \/\_\      \/\ \_\ \
//    \/\_____\      \/\_____\     /\_\/\_\      \/\_\      \/\_\ \_\
//     \/_____/       \/_____/     \/__\/_/       \/_/       \/_/ /_/
//
//   上海商创网络科技有限公司
//
//  ---------------------------------------------------------------------------------
//
//   一、协议的许可和权利
//
//    1. 您可以在完全遵守本协议的基础上，将本软件应用于商业用途；
//    2. 您可以在协议规定的约束和限制范围内修改本产品源代码或界面风格以适应您的要求；
//    3. 您拥有使用本产品中的全部内容资料、商品信息及其他信息的所有权，并独立承担与其内容相关的
//       法律义务；
//    4. 获得商业授权之后，您可以将本软件应用于商业用途，自授权时刻起，在技术支持期限内拥有通过
//       指定的方式获得指定范围内的技术支持服务；
//
//   二、协议的约束和限制
//
//    1. 未获商业授权之前，禁止将本软件用于商业用途（包括但不限于企业法人经营的产品、经营性产品
//       以及以盈利为目的或实现盈利产品）；
//    2. 未获商业授权之前，禁止在本产品的整体或在任何部分基础上发展任何派生版本、修改版本或第三
//       方版本用于重新开发；
//    3. 如果您未能遵守本协议的条款，您的授权将被终止，所被许可的权利将被收回并承担相应法律责任；
//
//   三、有限担保和免责声明
//
//    1. 本软件及所附带的文件是作为不提供任何明确的或隐含的赔偿或担保的形式提供的；
//    2. 用户出于自愿而使用本软件，您必须了解使用本软件的风险，在尚未获得商业授权之前，我们不承
//       诺提供任何形式的技术支持、使用担保，也不承担任何因使用本软件而产生问题的相关责任；
//    3. 上海商创网络科技有限公司不对使用本产品构建的商城中的内容信息承担责任，但在不侵犯用户隐
//       私信息的前提下，保留以任何方式获取用户信息及商品信息的权利；
//
//   有关本产品最终用户授权协议、商业授权与技术服务的详细内容，均由上海商创网络科技有限公司独家
//   提供。上海商创网络科技有限公司拥有在不事先通知的情况下，修改授权协议的权力，修改后的协议对
//   改变之日起的新授权用户生效。电子文本形式的授权协议如同双方书面签署的协议一样，具有完全的和
//   等同的法律效力。您一旦开始修改、安装或使用本产品，即被视为完全理解并接受本协议的各项条款，
//   在享有上述条款授予的权力的同时，受到相关的约束和限制。协议许可范围以外的行为，将直接违反本
//   授权协议并构成侵权，我们有权随时终止授权，责令停止损害，并保留追究相关责任的权力。
//
//  ---------------------------------------------------------------------------------
//
defined('IN_ECJIA') or exit('No permission resources.');

use Ecjia\App\Cron\CronAbstract;
use Ecjia\App\Affiliate\AffiliateOrderCommission;
use Ecjia\App\Finance\AccountConstant;

/**
 * 订单自动分成
 */
class cron_affiliate_order extends CronAbstract
{
    
    /**
     * 计划任务执行方法
     */
    public function run() {

        $list = $this->getOrderList();
        if($list) {
            foreach ($list as $affiliate_log) {
                //分成
//                $log_id = intval($_GET['id']);
//                $affiliate_log = RC_DB::table('affiliate_log')->where('log_id', $log_id)->first();
                if ($affiliate_log['separate_type'] == 1) {
                    return $this->showmessage('该订单已分成', ecjia::MSGTYPE_JSON | ecjia::MSGSTAT_ERROR);
                }
                $change_type = AccountConstant::BALANCE_AFFILIATE;
//                if($affiliate_log['agencysale_store_id']) {
//                    $change_type = AccountConstant::BALANCE_AGENCYSALE_AFFILIATE;
//                }
                Ecjia\App\Affiliate\OrderAffiliate::OrderAffiliateChangeAccount($affiliate_log, $change_type);
            }
        }

    }

    private function getOrderList() {
        //未分成订单
        $affiliate = unserialize(ecjia::config('affiliate'));
        empty($affiliate) && $affiliate = array();
        $affiliate_order_pass_days = $affiliate['affiliate_order_pass_days'];//订单多久后分成

        $filter['status'] = 0;

        $db_order_info_view = RC_DB::table('affiliate_log as a')
            ->leftJoin('order_info as o', RC_DB::raw('o.order_id'), '=', RC_DB::raw('a.order_id'))
//            ->leftJoin('store_franchisee as s', RC_DB::raw('s.store_id'), '=', RC_DB::raw('o.store_id'))
            ->leftJoin('users as u', RC_DB::raw('o.user_id'), '=', RC_DB::raw('u.user_id'));

        $separate_by = $affiliate['config']['separate_by'];

        $sqladd = '';

        //TODO
        if (!empty($affiliate['on'])) {
            if (empty($separate_by)) {
                $where = "o.user_id > 0 AND (u.parent_id > 0 AND o.is_separate = 0 OR o.is_separate > 0) $sqladd";
            } else {
                $where = "o.user_id > 0 AND (o.parent_id > 0 AND o.is_separate = 0 OR o.is_separate > 0) $sqladd";
            }
        } else {
            $where = "o.user_id > 0 AND o.is_separate > 0 $sqladd";
        }
        $db_order_info_view->whereRaw($where)->where( RC_DB::raw('o.shipping_status'), SS_RECEIVED);
        if($affiliate_order_pass_days) {
            $db_order_info_view->where( RC_DB::raw('a.time'), '<', RC_Time::gmtime() - 86400 * $affiliate_order_pass_days);
        }

        $db_order_info_view->where(RC_DB::raw('separate_type'), $filter['status']);

//        $field = "a.*, o.agencysale_store_id";
        $field = "a.*";
        $db_order_info_view->select(RC_DB::raw($field));
        $db_order_info_view->orderBy(RC_DB::raw('o.order_id'), 'desc');
        return $list = $db_order_info_view->get();
    }
    
    /**
     * 获取插件代号
     *
     * @see \Ecjia\System\Plugin\PluginInterface::getCode()
     */
    public function getCode()
    {
        return $this->loadConfig('cron_code');
    }
    
    /**
     * 加载配置文件
     *
     * @see \Ecjia\System\Plugin\PluginInterface::loadConfig()
     */
    public function loadConfig($key = null, $default = null)
    {
        return $this->loadPluginData(RC_Plugin::plugin_dir_path(__FILE__) . 'config.php', $key, $default);
    }
    

}

// end