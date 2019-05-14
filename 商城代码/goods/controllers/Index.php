<?php
//zend by  QQ:2172298892 瑾梦网络
namespace app\http\goods\controllers;

class Index extends \app\http\base\controllers\Frontend
{
	private $user_id = 0;
	private $goods_id = 0;
	private $region_id = 0;
	private $area_info = array();

	public function __construct()
	{
		parent::__construct();
		l(require LANG_PATH . c('shop.lang') . '/goods.php');
		$this->size = 10;
		$this->goods_id = i('id', 0, 'intval');

		if ($this->goods_id == 0) {
			ecs_header("Location: ./\n");
			exit();
		}

		$this->user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
		$this->assign('goods_id', $this->goods_id);
		$this->init_params();

		$this->assign('custom', c(custom));
	}
   //商品的详情
	public function actionIndex()
	{
		//$pid = i('request.pid', 0, 'intval');
		//门店id---用于获取门店信息
		$storeId = i('request.store_id', 0, 'intval');
		if (!empty($storeId)) {
			$_SESSION['store_id'] = $storeId;
		}
		else {
			unset($_SESSION['store_id']);
		}
        //门店信息
        if (0 < $storeId) {
            $sql = 'SELECT id, stores_name, stores_user FROM {pre}offline_store  WHERE id = \'' . $storeId . '\'';
            $store = $this->db->getRow($sql);
            $this->assign('store', $store);
        }
		//用户标识
		if (!empty($_SESSION['user_id'])) {
			$sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';
		}
		else {
			$sess_id = ' session_id = \'' . real_cart_mac_ip() . '\' ';
		}
        //未知
        $_SESSION['goods_equal'] = '';
        $this->db->query('delete from ' . $this->ecs->table('cart_combo') . ' WHERE (parent_id = 0 and goods_id = \'' . $this->goods_id . '\' or parent_id = \'' . $this->goods_id . '\') and ' . $sess_id);

		//是否为共享商品--共享商品叫‘缴纳押金’
		$this->assign('buy_goods', in_array($this->goods_id, array(1052, 1101))  ? '缴付押金' : '立即购买');
		//获取商品的一些详情
		$goods = get_goods_info($this->goods_id, $this->region_id, $this->area_info['region_id']);
        //登录验证
		if (empty($goods['user_id']) && !empty($this->user_id) && (isset($_GET['u']) === FALSE)) {
			$this->redirect('goods/index/index', array('id' => $this->goods_id, 'u' => $this->user_id));
		}
        //未知
		if (is_dir(APP_DRP_PATH)) {
			$isdrp = $this->model->table('drp_config')->field('value')->where(array('code' => 'isdrp'))->find();
			$sql = 'SELECT id FROM {pre}drp_shop WHERE audit=1 AND status=1 AND user_id=' . $this->user_id;
			$drp = $this->db->getOne($sql);
			$this->assign('drp', $drp);
			$this->assign('isdrp', $isdrp['value']);
		}
		//商品不存在
		if (($goods === false) || !isset($goods['goods_name'])) {
			ecs_header("Location: ./\n");
			exit();
		}
		if ($this->area_info['region_id'] == NULL) {
			$this->area_info['region_id'] = 0;
		}
		//商品的促销与限购信息
		$this->assign('promotion', get_promotion_info($this->goods_id, $goods['user_id']));
		$this->assign('promotion_info', get_promotion_info('', $goods['user_id']));
		$start_date = $goods['xiangou_start_date'];
		$end_date = $goods['xiangou_end_date'];
		$nowTime = gmtime();
		if (($start_date < $nowTime) && ($nowTime < $end_date)) {
			$xiangou = 1;
		}
		else {
			$xiangou = 0;
		}
		$order_goods = get_for_purchasing_goods($start_date, $end_date, $this->goods_id, $this->user_id);
		$this->assign('xiangou', $xiangou);
		$this->assign('orderG_number', $order_goods['goods_number']);
        //商品的经营店铺信息
		$shop_info = get_merchants_shop_info('merchants_steps_fields', $goods['user_id']);
		$adress = get_license_comp_adress($shop_info['license_comp_adress']);
		$this->assign('shop_info', $shop_info);
		$this->assign('adress', $adress);
		//该地区有仓库的省/市列表
		$province_list = get_warehouse_province();
		$this->assign('province_list', $province_list);
		//该省/市下的城/镇列表
		$city_list = get_region_city_county($this->province_id);
		if ($city_list) {
			foreach ($city_list as $k => $v) {
				$city_list[$k]['district_list'] = get_region_city_county($v['region_id']);
			}
		}
		$this->assign('city_list', $city_list);
		//地区列表
		$district_list = get_region_city_county($this->city_id);
		$this->assign('district_list', $district_list);
        //定位仓库相关
		$warehouse_list = get_warehouse_list_goods();
		$this->assign('warehouse_list', $warehouse_list);
		$warehouse_name = get_warehouse_name_id($this->region_id);
		$this->assign('warehouse_name', $warehouse_name);
		$this->assign('region_id', $this->region_id);
		$this->assign('user_id', $_SESSION['user_id']);
		$this->assign('shop_price_type', $goods['model_price']);
		$this->assign('area_id', $this->area_info['region_id']);
        //添加商品的品牌信息,如果有
		if (0 < $goods['brand_id']) {
			$brand_act = '';
			$brand = get_goods_brand($goods['brand_id']);

			if ($brand) {
				$goods['brand_id'] = $brand['brand_id'];
				$goods['goods_brand'] = $brand['goods_brand'];
				$brand_act = 'merchants_brands';
			}
			$goods['goods_brand_url'] = build_uri('brand', array('bid' => $goods['brand_id']), $goods['goods_brand']);
		}
		//添加商品可使用红包的金额,如果有
		if (0 < $goods['bonus_type_id']) {
			$time = gmtime();
			$sql = 'SELECT type_money FROM {pre}bonus_type' . ' WHERE type_id = \'' . $goods['bonus_type_id'] . '\' ' . ' AND send_type = \'' . SEND_BY_GOODS . '\' ' . ' AND send_start_date <= \'' . $time . '\'' . ' AND send_end_date >= \'' . $time . '\'';
			$goods['bonus_money'] = floatval($this->db->getOne($sql));

			if (0 < $goods['bonus_money']) {
				$goods['bonus_money'] = price_format($goods['bonus_money']);
			}
		}

		$sql = 'SELECT COUNT(*) FROM {pre}offline_store AS o LEFT JOIN {pre}store_goods AS s ON o.id = s.store_id WHERE s.goods_id = \'' . $this->goods_id . '\'';
		$goods['store_count'] = $this->db->getOne($sql);
        $goods['goods_style_name'] = add_style($goods['goods_name'], $goods['goods_name_style']);
        $this->assign('goods', $goods);
        $is_reality = get_goods_extends($this->goods_id);
        $this->assign('is_reality', $is_reality);
        $this->assign('id', $this->goods_id);
        $this->assign('type', 0);
        $this->assign('cfg', c('shop'));
		$this->assign('goods_id', $goods['goods_id']);
		$this->assign('promote_end_time', $goods['gmt_end_time']);
		$this->assign('categories', get_categories_tree($goods['cat_id']));
		$position = assign_ur_here($goods['cat_id'], $goods['goods_name']);
		$this->assign('page_title', $position['title']);
		$this->assign('keywords', $goods['keywords']);
		$this->assign('description', $goods['goods_brief']);
		$this->assign('page_img', get_wechat_image_path($goods['goods_img']));
		//获取商品的特性
		$properties = get_goods_properties($this->goods_id, $this->region_id, $this->area_info['region_id']);
		$this->assign('properties', $properties['pro']);
		//默认选中规格
		$default_spe = '';
		if ($properties['spe']) {
			foreach ($properties['spe'] as $k => $v) {
				if ($v['attr_type'] == 1) {
					if (0 < $v['is_checked']) {
						foreach ($v['values'] as $key => $val) {
							$default_spe .= ($val['checked'] ? $val['label'] . '、' : '');
						}
					}
					else {
						foreach ($v['values'] as $key => $val) {
							if ($key == 0) {
								$default_spe .= $val['label'] . '、';
							}
						}
					}
				}
			}
		}
		$this->assign('default_spe', $default_spe);
		//规格
		$this->assign('specification', $properties['spe']);
		//配件
		$fittings_list = get_goods_fittings(array($this->goods_id), $this->region_id, $this->area_info['region_id']);
//		if (is_array($fittings_list)) {
//			foreach ($fittings_list as $vo) {
//				$fittings_index[$vo['group_id']] = $vo['group_id'];
//			}
//		}
		$this->assign('fittings', $fittings_list);
		//礼包
		$package_goods_list = get_package_goods_list($goods['goods_id']);
		$this->assign('package_goods_list', $package_goods_list);
		assign_dynamic('goods');
		//批发计划
		$volume_price_list = get_volume_price_list($goods['goods_id'], '1');
		$this->assign('volume_price_list', $volume_price_list);
		//销量
		$this->assign('sales_count', get_goods_sales($this->goods_id));
        //运费
		$region = array(1, $this->province_id, $this->city_id, $this->district_id);
		$shippingFee = goodsshippingfee($this->goods_id, $this->region_id, $region);
		$this->assign('shippingFee', $shippingFee);
        //是否收藏
		if ($_SESSION['user_id']) {
			$where['user_id'] = $_SESSION['user_id'];
			$where['goods_id'] = $this->goods_id;
			$rs = $this->db->table('collect_goods')->where($where)->count();

			if (0 < $rs) {
				$this->assign('goods_collect', 1);
			}
		}
		//更新商品点击次数
		$this->db->query('UPDATE ' . $this->ecs->table('goods') . ' SET click_count = click_count + 1 WHERE goods_id = \'' . $this->goods_id . '\'');
        //存储商品浏览记录
		if (!empty($_COOKIE['ECS']['history_goods'])) {
			$history = explode(',', $_COOKIE['ECS']['history_goods']);
			array_unshift($history, $this->goods_id);
			$history = array_unique($history);
			while (c('shop.history_number') < count($history)) {
				array_pop($history);
			}
			cookie('ECS[history_goods]', implode(',', $history));
		}
		else {
			cookie('ECS[history_goods]', $this->goods_id);
		}
        //商品关联地区
		$this->assign('province_row', get_region_name($this->province_id));
		$this->assign('city_row', get_region_name($this->city_id));
		$this->assign('district_row', get_region_name($this->district_id));
		$goods_region['country'] = 1;
		$goods_region['province'] = $this->province_id;
		$goods_region['city'] = $this->city_id;
		$goods_region['district'] = $this->district_id;
		$this->assign('goods_region', $goods_region);
        //没用
		$date = array('shipping_code');
		$where = 'shipping_id = \'' . $goods['default_shipping'] . '\'';
		$shipping_code = get_table_date('shipping', $where, $date, 2);
		//购物车商品数量
		$cart_num = cart_number();
		$this->assign('cart_num', $cart_num);
		$this->assign('area_htmlType', 'goods');
		//评论及好评
		$mc_all = ments_count_all($this->goods_id);
		$mc_one = ments_count_rank_num($this->goods_id, 1);
		$mc_two = ments_count_rank_num($this->goods_id, 2);
		$mc_three = ments_count_rank_num($this->goods_id, 3);
		$mc_four = ments_count_rank_num($this->goods_id, 4);
		$mc_five = ments_count_rank_num($this->goods_id, 5);
		$comment_all = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);
		if (0 < $goods['user_id']) {
			$merchants_goods_comment = get_merchants_goods_comment($goods['user_id']);
			$this->assign('merch_cmt', $merchants_goods_comment);
		}
		$this->assign('comment_all', $comment_all);
		$good_comment = get_good_comment($this->goods_id, 4, 1, 0, 1);
		$this->assign('good_comment', $good_comment);
		//店铺收藏
		$sql = 'SELECT count(*) FROM ' . $this->ecs->table('collect_store') . ' WHERE ru_id = ' . $goods['user_id'];
		$collect_number = $this->db->getOne($sql);
		$this->assign('collect_number', $collect_number ? $collect_number : 0);
		//经营该商品的商家的一些信息
		$sql = 'select b.is_IM,a.ru_id,a.province, a.city, a.kf_type, a.kf_ww, a.kf_qq, a.meiqia, a.shop_name, a.kf_appkey,kf_secretkey from {pre}seller_shopinfo as a left join {pre}merchants_shop_information as b on a.ru_id=b.user_id where a.ru_id=\'' . $goods['user_id'] . '\' ';
		$basic_info = $this->db->getRow($sql);
		$info_ww = ($basic_info['kf_ww'] ? explode("\r\n", $basic_info['kf_ww']) : '');
		$info_qq = ($basic_info['kf_qq'] ? explode("\r\n", $basic_info['kf_qq']) : '');
		$kf_ww = ($info_ww ? $info_ww[0] : '');
		$kf_qq = ($info_qq ? $info_qq[0] : '');
		$basic_ww = ($kf_ww ? explode('|', $kf_ww) : '');
		$basic_qq = ($kf_qq ? explode('|', $kf_qq) : '');
		$basic_info['kf_ww'] = $basic_ww ? $basic_ww[1] : '';
		$basic_info['kf_qq'] = $basic_qq ? $basic_qq[1] : '';
		if ((($basic_info['is_im'] == 1) || ($basic_info['ru_id'] == 0)) && !empty($basic_info['kf_appkey'])) {
			$basic_info['kf_appkey'] = $basic_info['kf_appkey'];
		}
		else {
			$basic_info['kf_appkey'] = '';
		}
		$basic_date = array('region_name');
		$basic_info['province'] = get_table_date('region', 'region_id = \'' . $basic_info['province'] . '\'', $basic_date, 2);
		$basic_info['city'] = get_table_date('region', 'region_id= \'' . $basic_info['city'] . '\'', $basic_date, 2) . '市';
		$this->assign('basic_info', $basic_info);
        //关联商品、历史商品、类似商品、最新商品、链接商品--只有最新商品不为空
        $linked_goods = get_linked_goods($this->goods_id, $this->region_id, $this->area_info['region_id']);
        $history_goods = get_history_goods($this->goods_id, $this->region_id, $this->area_info['region_id']);
        $attribute_linked = get_same_attribute_goods($properties);
        $new_goods = get_recommend_goods('new', '', $this->region_id, $this->area_info['region_id'], $goods['user_id']);
        $link_goods = get_linked_goods($this->goods_id, $this->region_id, $this->area_info['region_id']);

        $this->assign('attribute_linked', $attribute_linked);
        $this->assign('history_goods', $history_goods);
        $this->assign('related_goods', $linked_goods);
        $this->assign('new_goods', $new_goods);
        $this->assign('link_goods', $link_goods);
        //可用物流列表
        $shipping_list = warehouse_shipping_list($goods, $this->region_id, 1, $goods_region);
        $this->assign('shipping_list', $shipping_list);
        //不同等级的会员价格
        $shop_price = ($goods['shop_price'] ? $goods['shop_price'] : 0);
        $this->assign('rank_prices', get_user_rank_prices($this->goods_id, $shop_price));
        //商品图
        $this->assign('pictures', get_goods_gallery($this->goods_id));
        //已购买商品
        $this->assign('bought_goods', get_also_bought($this->goods_id));
        //商品等级--不知道有什么用
        $this->assign('goods_rank', get_goods_rank($this->goods_id));
        //购物车数量
        $this->assign('cart_number', cart_number());
		//可领取商品优惠券--商品、等级满足条件
		$time = gmtime();
		$sql = 'SELECT * FROM {pre}coupons WHERE (`cou_type` = 3 OR `cou_type` = 4 ) AND `cou_end_time` >' . $time . ' AND (( instr(`cou_goods`, ' . $this->goods_id . ') ) or (`cou_goods`=0)) AND (( instr(`cou_ok_user`, ' . $_SESSION['user_rank'] . ') ) or (`cou_ok_user`=0)) and ru_id=' . $goods[user_id];
		$cache_id = md5($sql);
		$coupont = cache($cache_id);
		if ($coupont === false) {
			$coupont = $this->db->getALl($sql);
			foreach ($coupont as $key => $value) {
				$coupont[$key]['cou_end_time'] = date('Y.m.d', $value['cou_end_time']);
				$coupont[$key]['cou_start_time'] = date('Y.m.d', $value['cou_start_time']);
			}
			cache($cache_id, $coupont, 600);
		}
		$this->assign('bonus_list', $coupont);
        //分享商品
		$this->assign('shareContent', json_encode(array('title'=>$goods['goods_name'], 'desc'=>$goods['keywords'], 'link' => $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'], 'imgUrl' => get_wechat_image_path(ltrim($goods['goods_img'], '/')))));
		$this->display();
	}

	private function actionClearStoreId()
	{
		$result = array('error' => 0);
		unset($_SESSION['store_id']);
		echo json_encode($result);
	}

	public function actionInfo()
	{
		$info = $this->db->table('goods')->field('goods_desc,desc_mobile')->where(array('goods_id' => $this->goods_id))->find();
		$properties = get_goods_properties($this->goods_id, $this->region_id, $this->area_info['region_id']);
		$sql = 'SELECT ld.goods_desc FROM {pre}link_desc_goodsid AS dg, {pre}link_goods_desc AS ld WHERE dg.goods_id = ' . $this->goods_id . '  AND dg.d_id = ld.id';
		$link_desc = $this->db->getOne($sql);

		if (!empty($info['desc_mobile'])) {
			$goods_desc = preg_replace('/<div[^>]*(tools)[^>]*>(.*?)<\\/div>(.*?)<\\/div>/is', '', $info['desc_mobile']);
		}

		if (empty($info['desc_mobile']) && !empty($info['goods_desc'])) {
			$goods_desc = str_replace('src="images/upload', 'src="' . __STATIC__ . '/images/upload', $info['goods_desc']);
		}

		if (empty($info['desc_mobile']) && empty($info['goods_desc'])) {
			$goods_desc = $link_desc;
		}

		$this->assign('goods_desc', $goods_desc);
		$this->assign('properties', $properties['pro']);
		$this->assign('page_title', l('goods_detail'));
		$this->display();
	}

	public function actionComment($img = 0)
	{
		if (IS_AJAX) {
			$rank = i('rank', '');
			$page = i('page');
			$page = ($page - 1) * $this->size;

			if ($rank == 'img') {
				$rank = 5;
				$img = 1;
			}

			$arr = get_good_comment_as($this->goods_id, $rank, 1, $page);
			$comments = $arr['arr'];

			if ($img) {
				foreach ($comments as $key => $val) {
					if ($val['thumb'] == 0) {
						unset($comments[$key]);
					}
				}

				$rank = 'img';
			}

			$show = (0 < count($comments) ? 1 : 0);
			$max = (0 < $page ? 0 : 1);
			exit(json_encode(array('comments' => $comments, 'rank' => $rank, 'show' => $show, 'reset' => $max, 'totalPage' => $arr['max'], 'top' => 1)));
		}

		$this->assign('img', $img);
		$this->assign('info', commentcol($this->goods_id));
		$this->assign('id', $this->goods_id);
		$this->assign('page_title', l('goods_comment'));
		$this->display('comment');
	}

	public function actionInfoimg()
	{
		$this->actionComment(1);
	}

	public function actionPrice()
	{
		$res = array('err_msg' => '', 'result' => '', 'qty' => 1);
		$attr = i('attr');
		$number = i('number', 1, 'intval');
		$attr_id = (!empty($attr) ? explode(',', $attr) : array());
		$warehouse_id = i('request.warehouse_id', 0, 'intval');
		$area_id = i('request.area_id', 0, 'intval');
		$onload = i('request.onload', '', 'trim');
		$goods_attr = (isset($_REQUEST['goods_attr']) ? explode(',', $_REQUEST['goods_attr']) : array());
		$attr_ajax = get_goods_attr_ajax($this->goods_id, $goods_attr, $attr_id);
		$goods = get_goods_info($this->goods_id, $warehouse_id, $area_id);

		if ($this->goods_id == 0) {
			$res['err_msg'] = l('err_change_attr');
			$res['err_no'] = 1;
		}
		else {
			if ($number == 0) {
				$res['qty'] = $number = 1;
			}
			else {
				$res['qty'] = $number;
			}

			$products = get_warehouse_id_attr_number($this->goods_id, $_REQUEST['attr'], $goods['user_id'], $warehouse_id, $area_id);
			$attr_number = $products['product_number'];

			if ($goods['model_attr'] == 1) {
				$table_products = 'products_warehouse';
				$type_files = ' and warehouse_id = \'' . $warehouse_id . '\'';
			}
			else if ($goods['model_attr'] == 2) {
				$table_products = 'products_area';
				$type_files = ' and area_id = \'' . $area_id . '\'';
			}
			else {
				$table_products = 'products';
				$type_files = '';
			}

			$sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table($table_products) . ' WHERE goods_id = \'' . $this->goods_id . '\'' . $type_files . ' LIMIT 0, 1';
			$prod = $GLOBALS['db']->getRow($sql);

			if ($goods['goods_type'] == 0) {
				$attr_number = $goods['goods_number'];
			}
			else {
				if (empty($prod)) {
					$attr_number = $goods['goods_number'];
				}

				if (!empty($prod) && ($GLOBALS['_CFG']['add_shop_price'] == 0) && ($onload == 'onload')) {
					if (empty($attr_number)) {
						$attr_number = $goods['goods_number'];
					}
				}
			}

			$attr_number = (!empty($attr_number) ? $attr_number : 0);
			$res['attr_number'] = $attr_number;
			$res['limit_number'] = $attr_number < $number ? ($attr_number ? $attr_number : 1) : $number;
			$shop_price = get_final_price($this->goods_id, $number, true, $attr_id, $warehouse_id, $area_id);
			$res['shop_price'] = price_format($shop_price);
			$res['market_price'] = $goods['market_price'];
			$res['show_goods'] = 0;
			if ($goods_attr && ($GLOBALS['_CFG']['add_shop_price'] == 0)) {
				if (count($goods_attr) == count($attr_ajax['attr_id'])) {
					$res['show_goods'] = 1;
				}
			}

			$spec_price = get_final_price($this->goods_id, $number, true, $attr_id, $warehouse_id, $area_id, 1, 0, 0, $res['show_goods']);
			if (($GLOBALS['_CFG']['add_shop_price'] == 0) && empty($spec_price)) {
				$spec_price = $shop_price;
			}

			$res['spec_price'] = price_format($spec_price);
			$martetprice_amount = $spec_price + $goods['marketPrice'];
			$res['marketPrice_amount'] = price_format($spec_price + $goods['marketPrice']);
			$res['discount'] = round($shop_price / $martetprice_amount, 2) * 10;
			$res['result'] = price_format($shop_price * $number);

			if ($GLOBALS['_CFG']['add_shop_price'] == 0) {
				$res['result_market'] = price_format($goods['marketPrice'] * $number);
			}
			else {
				$res['result_market'] = price_format(($goods['marketPrice'] * $number) + $spec_price);
			}
		}

		$goods_fittings = get_goods_fittings_info($this->goods_id, $warehouse_id, $area_id, '', 1);
		$fittings_list = get_goods_fittings(array($this->goods_id), $warehouse_id, $area_id);

		if ($fittings_list) {
			if (is_array($fittings_list)) {
				foreach ($fittings_list as $vo) {
					$fittings_index[$vo['group_id']] = $vo['group_id'];
				}
			}

			ksort($fittings_index);
			$merge_fittings = get_merge_fittings_array($fittings_index, $fittings_list);
			$fitts = get_fittings_array_list($merge_fittings, $goods_fittings);

			for ($i = 0; $i < count($fitts); $i++) {
				$fittings_interval = $fitts[$i]['fittings_interval'];
				$res['fittings_interval'][$i]['fittings_minMax'] = price_format($fittings_interval['fittings_min']) . '-' . number_format($fittings_interval['fittings_max'], 2, '.', '');
				$res['fittings_interval'][$i]['market_minMax'] = price_format($fittings_interval['market_min']) . '-' . number_format($fittings_interval['market_max'], 2, '.', '');

				if ($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']) {
					$res['fittings_interval'][$i]['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']);
				}
				else {
					$res['fittings_interval'][$i]['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']) . '-' . number_format($fittings_interval['save_maxPrice'], 2, '.', '');
				}

				$res['fittings_interval'][$i]['groupId'] = $fittings_interval['groupId'];
			}
		}

		if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
			$area_list = get_goods_link_area_list($this->goods_id, $goods['user_id']);

			if ($area_list['goods_area']) {
				if (!in_array($area_id, $area_list['goods_area'])) {
					$res['err_no'] = 2;
				}
			}
			else {
				$res['err_no'] = 2;
			}
		}

		$attr_info = get_attr_value($this->goods_id, $attr_id[0]);

		if (!empty($attr_info['attr_img_flie'])) {
			$res['attr_img'] = get_image_path($attr_info['attr_img_flie']);
		}

		$res['onload'] = $onload;
		exit(json_encode($res));
	}

	public function actionInWarehouse()
	{
		if (IS_AJAX) {
			$res = array('err_msg' => '', 'result' => '', 'qty' => 1, 'goods_id' => 0);
			$pid = i('get.pid', 0, 'intval');
			$goods_id = i('get.id', 0, 'intval');
			if (empty($pid) || empty($goods_id)) {
				exit(json_encode($res));
			}

			cookie('region_id', $pid);
			cookie('regionid', $pid);
			$area_region = 0;
			cookie('area_region', $area_region);
			$res['goods_id'] = $goods_id;
			exit(json_encode($res));
		}
	}

	public function actionInstock()
	{
		if (IS_AJAX) {
			$res = array('err_msg' => '', 'result' => '', 'qty' => 1);
			clear_cache_files();
			$goods_id = $this->goods_id;
			$province = i('get.province', 1, 'intval');
			$city = i('get.city', 0, 'intval');
			$district = i('get.district', 0, 'intval');
			$street = i('get.street', 0, 'intval');
			$d_null = i('get.d_null', 0, 'intval');
			$user_id = i('get.user_id', 0, 'intval');
			$user_address = get_user_address_region($user_id);
			$user_address = explode(',', $user_address['region_address']);
			setcookie('province', $province, gmtime() + (3600 * 24 * 30));
			setcookie('city', $city, gmtime() + (3600 * 24 * 30));
			setcookie('district', $district, gmtime() + (3600 * 24 * 30));
			setcookie('street', $street, gmtime() + (3600 * 24 * 30));
			$regionId = 0;
			setcookie('regionId', $regionId, gmtime() + (3600 * 24 * 30));
			setcookie('type_province', 0, gmtime() + (3600 * 24 * 30));
			setcookie('type_city', 0, gmtime() + (3600 * 24 * 30));
			setcookie('type_district', 0, gmtime() + (3600 * 24 * 30));
			setcookie('type_street', 0, gmtime() + (3600 * 24 * 30));
			$res['d_null'] = $d_null;

			if ($d_null == 0) {
				if (in_array($district, $user_address)) {
					$res['isRegion'] = 1;
				}
				else {
					$res['message'] = l('write_address');
					$res['isRegion'] = 88;
				}
			}
			else {
				setcookie('district', '', gmtime() + (3600 * 24 * 30));
			}

			$res['goods_id'] = $goods_id;
			exit(json_encode($res));
		}
	}

	public function actionAddCollection()
	{
		$result = array('error' => 0, 'message' => '');
		if (!isset($this->user_id) || ($this->user_id == 0)) {
			$result['error'] = 2;
			$result['message'] = l('login_please');
			exit(json_encode($result));
		}
		else {
			$where['user_id'] = $this->user_id;
			$where['goods_id'] = $this->goods_id;
			$rs = $this->db->table('collect_goods')->where($where)->count();

			if (0 < $rs) {
				$rs = $this->db->table('collect_goods')->where($where)->delete();

				if (!$rs) {
					$result['error'] = 1;
					$result['message'] = m()->errorMsg();
					exit(json_encode($result));
				}
				else {
					$result['error'] = 0;
					$result['message'] = l('collect_success');
					exit(json_encode($result));
				}
			}
			else {
				$data['user_id'] = $this->user_id;
				$data['goods_id'] = $this->goods_id;
				$data['add_time'] = gmtime();

				if ($this->db->table('collect_goods')->data($data)->add() === false) {
					$result['error'] = 1;
					$result['message'] = m()->errorMsg();
					exit(json_encode($result));
				}
				else {
					$result['error'] = 0;
					$result['message'] = l('collect_success');
					exit(json_encode($result));
				}
			}
		}
	}

	private function init_params()
	{

        if (!isset($_COOKIE['province'])) {
			$area_array = get_ip_area_name();

			if ($area_array['county_level'] == 2) {
				$date = array('region_id', 'parent_id', 'region_name');
				$where = 'region_name = \'' . $area_array['area_name'] . '\' AND region_type = 2';
				$city_info = get_table_date('region', $where, $date, 1);
				$date = array('region_id', 'region_name');
				$where = 'region_id = \'' . $city_info[0]['parent_id'] . '\'';
				$province_info = get_table_date('region', $where, $date);
				$where = 'parent_id = \'' . $city_info[0]['region_id'] . '\' order by region_id asc limit 0, 1';
				$district_info = get_table_date('region', $where, $date, 1);
			}
			else if ($area_array['county_level'] == 1) {
				$area_name = $area_array['area_name'];
				$date = array('region_id', 'region_name');
				$where = 'region_name = \'' . $area_name . '\'';
				$province_info = get_table_date('region', $where, $date);
				$where = 'parent_id = \'' . $province_info['region_id'] . '\' order by region_id asc limit 0, 1';
				$city_info = get_table_date('region', $where, $date, 1);
				$where = 'parent_id = \'' . $city_info[0]['region_id'] . '\' order by region_id asc limit 0, 1';
				$district_info = get_table_date('region', $where, $date, 1);
			}
		}


		$order_area = get_user_order_area($this->user_id);
		$user_area = get_user_area_reg($this->user_id);
		if ($order_area['province'] && (0 < $this->user_id)) {
			$this->province_id = $order_area['province'];
			$this->city_id = $order_area['city'];
			$this->district_id = $order_area['district'];
		}
		else {
			if (0 < $user_area['province']) {
				$this->province_id = $user_area['province'];
				cookie('province', $user_area['province']);
				$this->region_id = get_province_id_warehouse($this->province_id);
			}
			else {
				$sql = 'select region_name from ' . $this->ecs->table('region_warehouse') . ' where regionId = \'' . $province_info['region_id'] . '\'';
				$warehouse_name = $this->db->getOne($sql);
				$this->province_id = $province_info['region_id'];
				$cangku_name = $warehouse_name;
				$this->region_id = get_warehouse_name_id(0, $cangku_name);
			}

			if (0 < $user_area['city']) {
				$this->city_id = $user_area['city'];
				cookie('city', $user_area['city']);
			}
			else {
				$this->city_id = $city_info[0]['region_id'];
			}

			if (0 < $user_area['district']) {
				$this->district_id = $user_area['district'];
				cookie('district', $user_area['district']);
			}
			else {
				$this->district_id = $district_info[0]['region_id'];
			}
		}

		$this->province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $this->province_id;
		$child_num = get_region_child_num($this->province_id);

		if (0 < $child_num) {
			$this->city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $this->city_id;
		}
		else {
			$this->city_id = '';
		}

		$child_num = get_region_child_num($this->city_id);

		if (0 < $child_num) {
			$this->district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $this->district_id;
		}
		else {
			$this->district_id = '';
		}

		$this->region_id = !isset($_COOKIE['region_id']) ? $this->region_id : $_COOKIE['region_id'];
		$goods_warehouse = get_warehouse_goods_region($this->province_id);

		if ($goods_warehouse) {
			$this->regionId = $goods_warehouse['region_id'];
			if ($_COOKIE['region_id'] && $_COOKIE['regionid']) {
				$gw = 0;
			}
			else {
				$gw = 1;
			}
		}

		if ($gw) {
			$this->region_id = $this->regionId;
			cookie('area_region', $this->region_id);
		}

		cookie('goodsId', $this->goods_id);
		$sellerInfo = get_seller_info_area();

		if (empty($this->province_id)) {
			$this->province_id = $sellerInfo['province'];
			$this->city_id = $sellerInfo['city'];
			$this->district_id = 0;
			cookie('province', $this->province_id);
			cookie('city', $this->city_id);
			cookie('district', $this->district_id);
			$this->region_id = get_warehouse_goods_region($this->province_id);
		}
		$this->area_info = get_area_info($this->province_id);
	}

	public function actionCheckDrp()
	{
		if (IS_AJAX) {
			$shop_num = $this->model->table('drp_shop')->where(array('user_id' => $this->user_id))->count();

			if ($shop_num == 1) {
				exit(json_encode(array('code' => 1)));
			}
			else {
				exit(json_encode(array('code' => 0)));
			}
		}
	}
}

?>
