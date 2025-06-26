<?php
/**
 * AdStart.click - Real-Time Bidding (RTB) Server
 * Version: 20.0 (Fix RTB Bidding on VAST Zones)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

require_once __DIR__ . '/admin/db.php';

// =================================================================================
// BAGIAN 0: VAST CREATIVE SERVER
// =================================================================================
if (isset($_GET['get_vast']) && is_numeric($_GET['get_vast'])) {
    try {
        $vast_creative_id = (int)$_GET['get_vast'];
        $stmt = $pdo->prepare("SELECT * FROM vast_creatives WHERE id = ? AND status = 'active'");
        $stmt->execute([$vast_creative_id]);
        $creative = $stmt->fetch(PDO::FETCH_ASSOC);
        header("Content-Type: application/xml; charset=utf-8");
        if ($creative) {
            if ($creative['is_url']) {
                $cache_is_stale = !isset($creative['last_fetched']) || (new DateTime($creative['last_fetched']))->diff(new DateTime())->i >= 15;
                if (!$cache_is_stale && !empty($creative['cached_vast_xml'])) {
                    echo $creative['cached_vast_xml'];
                } else {
                    $vast_content = fetch_url_content($creative['vast_xml']);
                    if ($vast_content) {
                        $pdo->prepare("UPDATE vast_creatives SET cached_vast_xml = ?, last_fetched = NOW() WHERE id = ?")->execute([$vast_content, $vast_creative_id]);
                        echo $vast_content;
                    } else {
                        echo $creative['cached_vast_xml'] ?: '<VAST version="3.0"></VAST>';
                    }
                }
            } else {
                echo $creative['vast_xml'];
            }
        } else {
            echo '<VAST version="3.0"></VAST>';
        }
    } catch (Exception $e) {
        error_log("VAST Server Error: " . $e->getMessage());
        header("Content-Type: application/xml; charset=utf-8");
        echo '<VAST version="3.0"></VAST>';
    }
    exit;
}

// =================================================================================
// BAGIAN 0.5: ZONE VAST TAG SERVER (UNTUK PUBLISHER) - DIPERBAIKI
// =================================================================================
if (isset($_GET['zone_id']) && is_numeric($_GET['zone_id'])) {
    header_remove('Content-Type');
    $impression_id_for_log = uniqid('imp-zone-');

    try {
        $zone_id = (int)$_GET['zone_id'];
        $stmt_zone = $pdo->prepare("SELECT z.site_id, z.ad_type, s.domain FROM zones z JOIN sites s ON z.site_id = s.id WHERE z.id = ? AND z.status = 'active'");
        $stmt_zone->execute([$zone_id]);
        $zone_info = $stmt_zone->fetch(PDO::FETCH_ASSOC);

        if (!$zone_info || $zone_info['ad_type'] !== 'vast') {
            throw new Exception("Zone not found, not active, or not a VAST zone.");
        }
        
        // **PERBAIKAN**: Kumpulkan info device & geo dari pengguna yang meminta tag VAST
        $ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
        
        $device_object = [
            'ua' => $user_agent,
            'ip' => $ip_address,
            'geo' => ['country' => $country]
        ];

        $vast_impression_data = [
            'id' => $impression_id_for_log,
            'video' => [
                'mimes' => ['video/mp4', 'application/javascript'], 'w' => 640, 'h' => 480,
                'protocols' => [2, 3, 5, 6], 'linearity' => 1, 'skip' => 1
            ]
        ];
        
        // **PERBAIKAN**: Kirim data device ke fungsi lelang
        list($bidders, ) = run_internal_auction($pdo, $zone_info['site_id'], 'vast', $vast_impression_data, $device_object);
        
        if (empty($bidders)) { throw new Exception("No VAST bidders found for this zone."); }
        
        usort($bidders, fn($a, $b) => $b['price'] <=> $a['price']);
        $winner = $bidders[0];
        $adm_final = process_ad_markup($winner['adm'], 'vast', $impression_id_for_log);

        if(empty($adm_final)) { throw new Exception("Failed to generate final VAST markup."); }

        header("Content-Type: application/xml; charset=utf-8");
        echo $adm_final;

        try {
            $log_stmt = $pdo->prepare("INSERT INTO rtb_events (event_type, impression_id, demand_campaign_id, site_id, ad_format, country) VALUES ('win', ?, ?, ?, 'vast', ?)");
            $log_stmt->execute([ $impression_id_for_log, $winner['campaign']['id'], $zone_info['site_id'], $country ]);
        } catch(Exception $log_e) {
            error_log("AdStart VAST Zone Logging Failed: " . $log_e->getMessage());
        }

    } catch (Exception $e) {
        error_log("AdStart VAST Zone Error: " . $e->getMessage());
        header("Content-Type: application/xml; charset=utf-8");
        echo '<VAST version="3.0"><Errors><Error><![CDATA['.$e->getMessage().']]></Error></Errors></VAST>';
    }
    exit;
}


// Sisa dari file (BAGIAN 1, BAGIAN 2, dan HELPER FUNCTIONS) tetap sama
// ... (kode yang ada di sini tidak perlu diubah) ...
// Namun, saya akan menyertakan seluruh file untuk kelengkapan, dengan perubahan pada function signature.


// =================================================================================
// BAGIAN 1: DEMAND SIDE (INTERNAL)
// =================================================================================
if (isset($_GET['site_id']) && is_numeric($_GET['site_id'])) {
    header_remove('Content-Type');
    $impression_id_for_log = uniqid('imp-');

    try {
        $site_id = (int)$_GET['site_id'];
        $req_type = $_GET['type'] ?? 'banner';
        list($bidders, $format_id) = run_internal_auction($pdo, $site_id, $req_type, $_GET);
        
        if (empty($bidders)) { throw new Exception("No bidders found for this request."); }
        
        usort($bidders, fn($a, $b) => $b['price'] <=> $a['price']);
        
        $winner = $bidders[0];
        $adm_final = process_ad_markup($winner['adm'], $winner['campaign']['ad_type'], $impression_id_for_log);
        
        if ($winner['campaign']['ad_type'] === 'vast') {
            header("Content-Type: application/xml; charset=utf-8");
        }
        echo $adm_final;

        try {
            $log_stmt = $pdo->prepare("INSERT INTO rtb_events (event_type, impression_id, demand_campaign_id, site_id, ad_format) VALUES ('bid', ?, ?, ?, ?)");
            $log_stmt->execute([ $impression_id_for_log, $winner['campaign']['id'], $site_id, $req_type ]);
        } catch(Exception $log_e) { error_log("AdStart Bid Logging Failed: " . $log_e->getMessage()); }

    } catch (Exception $e) {
        header("HTTP/1.1 204 No Content");
        error_log("AdStart Demand-Side Error: " . $e->getMessage());
    }
    exit;
}


// =================================================================================
// BAGIAN 2: SUPPLY SIDE (SSP)
// =================================================================================
if (isset($_GET['ep'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("HTTP/1.1 405 Method Not Allowed"); exit; }
    $bid_request = json_decode(file_get_contents('php://input'), true);
    if (!$bid_request) { exit; }

    $impression_id = $bid_request['imp'][0]['id'] ?? uniqid('err-');
    $site_id_req = $bid_request['site']['id'] ?? null;
    $country_req = $bid_request['device']['geo']['country'] ?? null;
    $endpoint_info = null;

    try {
        $req_type = isset($bid_request['imp'][0]['video']) ? 'vast' : 'banner';
        $pdo->prepare("INSERT INTO rtb_events (event_type, impression_id, site_id, country, ad_format) VALUES ('request', ?, ?, ?, ?)")->execute([$impression_id, $site_id_req, $country_req, $req_type]);
        
        $stmt_ep = $pdo->prepare("SELECT * FROM rtb_endpoints_generated WHERE endpoint_hash = ? AND status = 'active'");
        $stmt_ep->execute([$_GET['ep']]);
        $endpoint_info = $stmt_ep->fetch();
        if (!$endpoint_info) { throw new Exception("Endpoint not found or is paused."); }

        $stmt_partner_deal = $pdo->prepare("SELECT revenue_share FROM publishers WHERE id = ?");
        $stmt_partner_deal->execute([$endpoint_info['publisher_id']]);
        $partner_deal = $stmt_partner_deal->fetch();
        if (!$partner_deal) { throw new Exception("Deal for partner not found."); }
        $partner_revenue_share_percent = (float)$partner_deal['revenue_share'];

        $impression = $bid_request['imp'][0] ?? null;
        if (!$impression) { throw new Exception("Impression object missing."); }
        if ($req_type !== $endpoint_info['ad_format']) { throw new Exception("Request format does not match endpoint rule."); }
        
        $format_id = null;
        if ($req_type === 'banner') {
            $stmt_format = $pdo->prepare("SELECT id FROM ad_formats WHERE width = ? AND height = ?");
            $stmt_format->execute([$impression['banner']['w'] ?? 0, $impression['banner']['h'] ?? 0]);
            $format = $stmt_format->fetch();
            if ($format) { $format_id = $format['id']; } else { throw new Exception("Unsupported ad size."); }
        }

        list($bidders, ) = run_ssp_auction($pdo, $req_type, $format_id, $bid_request);
        if (empty($bidders)) { throw new Exception("No demand found for this request after auction."); }

        usort($bidders, fn($a, $b) => $b['price'] <=> $a['price']);
        $winner = $bidders[0];
        $final_adm = process_ad_markup($winner['adm'], $winner['campaign']['ad_type'], $impression['id']);
        if (empty($final_adm)) { throw new Exception("Failed to process winning ADM."); }
        
        $final_bid_price = calculate_final_bid_price($winner['price'], $partner_revenue_share_percent, $endpoint_info['bid_price_is_cpm']);
        if ($final_bid_price <= 0) { throw new Exception("Final bid price is zero or less after arbitrage."); }

        $bid_response = create_bid_response($bid_request['id'], $impression['id'], $final_bid_price, $final_adm, $winner['campaign']['id'], $winner['source']);
        
        try {
            $pdo->prepare("INSERT INTO rtb_events (event_type, impression_id, supply_endpoint_id, demand_campaign_id, site_id, country, bid_price, payout_price) VALUES ('bid', ?, ?, ?, ?, ?, ?, ?)")->execute([$impression['id'], $endpoint_info['id'], $winner['campaign']['id'], $site_id_req, $country_req, $winner['price'], $final_bid_price]);
        } catch(Exception $log_e) { error_log("SSP Event Logging Failed: " . $log_e->getMessage()); }

        echo json_encode($bid_response);

    } catch (Exception $e) {
        $pdo->prepare("INSERT INTO rtb_events (event_type, impression_id, supply_endpoint_id, site_id, country, error_message) VALUES ('error', ?, ?, ?, ?, ?)")->execute([ $impression_id, $endpoint_info['id'] ?? null, $site_id_req, $country_req, $e->getMessage() ]);
        header("HTTP/1.1 204 No Content");
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid Request.']);


// =================================================================================
// HELPER FUNCTIONS
// =================================================================================

function run_internal_auction($pdo, $site_id, $req_type, $imp_data, $device_data = null) {
    $bidders = [];
    $format_id = null;
    $site_info = $pdo->query("SELECT * FROM sites WHERE id = $site_id")->fetch(PDO::FETCH_ASSOC);

    if ($req_type === 'banner') {
        $stmt_format = $pdo->prepare("SELECT id FROM ad_formats WHERE width = ? AND height = ?");
        $stmt_format->execute([$imp_data['w'] ?? 0, $imp_data['h'] ?? 0]);
        $format = $stmt_format->fetch();
        if ($format) { $format_id = $format['id']; } else { return [[], null]; }
    }
    
    // RON Campaigns
    $stmt_ron = $pdo->prepare("SELECT * FROM campaigns c " . ($req_type === 'banner' ? "JOIN campaign_formats cf ON c.id = cf.campaign_id WHERE cf.format_id = ?" : "WHERE 1=1") . " AND c.status = 'active' AND c.campaign_type = 'ron' AND c.ad_type = ?");
    $stmt_ron->execute($req_type === 'banner' ? [$format_id, $req_type] : [$req_type]);
    foreach ($stmt_ron->fetchAll(PDO::FETCH_ASSOC) as $rc) {
        if (!empty($rc['ron_bid_cpm'])) { $bidders[] = ['price' => (float)$rc['ron_bid_cpm'], 'campaign' => $rc, 'source' => 'ron', 'adm' => $rc['ron_adm']]; }
    }

    // RTB Campaigns
    $stmt_rtb = $pdo->prepare("SELECT * FROM campaigns c " . ($req_type === 'banner' ? "JOIN campaign_formats cf ON c.id = cf.campaign_id WHERE cf.format_id = ?" : "WHERE 1=1") . " AND c.status = 'active' AND c.campaign_type = 'rtb' AND c.ad_type = ?");
    $stmt_rtb->execute($req_type === 'banner' ? [$format_id, $req_type] : [$req_type]);
    $rtb_campaigns = $stmt_rtb->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rtb_campaigns)) {
        $bid_request_payload = ['id' => uniqid(), 'imp' => [$imp_data], 'site' => $site_info];
        // **PERBAIKAN**: Tambahkan objek device jika tersedia
        if ($device_data) {
            $bid_request_payload['device'] = $device_data;
        }

        $mh = curl_multi_init();
        $curl_handles = [];
        foreach ($rtb_campaigns as $rtc) {
            if(empty($rtc['rtb_endpoint_url'])) continue;
            $ch = curl_init($rtc['rtb_endpoint_url']);
            curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => json_encode($bid_request_payload), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-openrtb-version: 2.5'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 1]);
            curl_multi_add_handle($mh, $ch);
            $curl_handles[(int)$ch] = $rtc;
        }
        $running = null;
        do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);
        while ($info = curl_multi_info_read($mh)) {
            if ($info['msg'] == CURLMSG_DONE) {
                $ch = $info['handle'];
                $response_body = curl_multi_getcontent($ch);
                $rtb_campaign_data = $curl_handles[(int)$ch] ?? null;
                if ($response_body && $rtb_campaign_data) {
                    $response_json = json_decode($response_body, true);
                    $real_bid = $response_json['seatbid'][0]['bid'][0] ?? null;
                    if ($real_bid && !empty($real_bid['adm']) && $real_bid['price'] > 0) {
                        $bidders[] = ['price' => (float)$real_bid['price'], 'campaign' => $rtb_campaign_data, 'adm' => $real_bid['adm'], 'source' => 'rtb'];
                    }
                }
                curl_multi_remove_handle($mh, $ch);
            }
        }
        curl_multi_close($mh);
    }
    return [$bidders, $format_id];
}

function run_ssp_auction($pdo, $req_type, $format_id, $bid_request) {
    $bidders = [];
    $impression = $bid_request['imp'][0];
    
    // RON Campaigns
    $stmt_ron = $pdo->prepare("SELECT * FROM campaigns c " . ($req_type === 'banner' ? "JOIN campaign_formats cf ON c.id = cf.campaign_id WHERE cf.format_id = ?" : "WHERE 1=1") . " AND c.status = 'active' AND c.campaign_type = 'ron' AND c.ad_type = ?");
    $stmt_ron->execute($req_type === 'banner' ? [$format_id, $req_type] : [$req_type]);
    foreach ($stmt_ron->fetchAll(PDO::FETCH_ASSOC) as $rc) {
        if (!empty($rc['ron_bid_cpm'])) { $bidders[] = ['price' => (float)$rc['ron_bid_cpm'], 'campaign' => $rc, 'source' => 'ron', 'adm' => $rc['ron_adm']]; }
    }

    // RTB Campaigns
    $stmt_rtb = $pdo->prepare("SELECT * FROM campaigns c " . ($req_type === 'banner' ? "JOIN campaign_formats cf ON c.id = cf.campaign_id WHERE cf.format_id = ?" : "WHERE 1=1") . " AND c.status = 'active' AND c.campaign_type = 'rtb' AND c.ad_type = ?");
    $stmt_rtb->execute($req_type === 'banner' ? [$format_id, $req_type] : [$req_type]);
    $rtb_campaigns = $stmt_rtb->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rtb_campaigns)) {
        $mh = curl_multi_init();
        $curl_handles = [];
        foreach ($rtb_campaigns as $rtc) {
            if(empty($rtc['rtb_endpoint_url'])) continue;
            $ch = curl_init($rtc['rtb_endpoint_url']);
            curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => json_encode($bid_request), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-openrtb-version: 2.5'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 1]);
            curl_multi_add_handle($mh, $ch);
            $curl_handles[(int)$ch] = $rtc;
        }
        $running = null;
        do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);
        while ($info = curl_multi_info_read($mh)) {
            if ($info['msg'] == CURLMSG_DONE) {
                $ch = $info['handle'];
                $response_body = curl_multi_getcontent($ch);
                $rtb_campaign_data = $curl_handles[(int)$ch] ?? null;
                if ($response_body && $rtb_campaign_data) {
                    $response_json = json_decode($response_body, true);
                    $real_bid = $response_json['seatbid'][0]['bid'][0] ?? null;
                    if ($real_bid && !empty($real_bid['adm']) && $real_bid['price'] > 0) {
                        $bidders[] = ['price' => (float)$real_bid['price'], 'campaign' => $rtb_campaign_data, 'source' => 'rtb', 'adm' => $real_bid['adm']];
                    }
                }
                curl_multi_remove_handle($mh, $ch);
            }
        }
        curl_multi_close($mh);
    }
    return [$bidders, $format_id];
}

function process_ad_markup($adm_input, $ad_type, $impression_id) {
    if ($ad_type === 'vast') {
        $vast_xml_content = (filter_var($adm_input, FILTER_VALIDATE_URL)) ? fetch_url_content($adm_input) : $adm_input;
        if (empty(trim($vast_xml_content))) { return ''; }
        $pixel_url = "https://adstart.click/impression.php?id=" . urlencode($impression_id);
        $impression_node = "<Impression><![CDATA[{$pixel_url}]]></Impression>";
        $pos = strripos($vast_xml_content, '</Ad>');
        if ($pos === false) { $pos = strripos($vast_xml_content, '</VAST>'); }
        return ($pos !== false) ? substr_replace($vast_xml_content, $impression_node, $pos, 0) : $vast_xml_content;
    }
    return $adm_input . "<img src=\"https://adstart.click/impression.php?id=" . urlencode($impression_id) . "\" width=\"1\" height=\"1\" border=\"0\" alt=\"\" style=\"display:none;\"/>";
}

function calculate_final_bid_price($gross_cpm, $revenue_share_percent, $is_cpm_endpoint) {
    return ($is_cpm_endpoint ? 1000 : 1) * (($gross_cpm / 1000) * ($revenue_share_percent / 100));
}

function create_bid_response($request_id, $impression_id, $price, $adm, $campaign_id, $source) {
    return ["id" => $request_id, "seatbid" => [["seat" => "adstart.click", "bid" => [["id" => "bid-" . bin2hex(random_bytes(8)), "impid" => $impression_id, "price" => round($price, 6), "adm" => $adm, "cid" => (string)$campaign_id, "crid" => "creative-" . $campaign_id, "dealid" => ($source === 'ron' ? 'ron-deal' : 'rtb-deal')]]]]];
}

function fetch_url_content($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 2, CURLOPT_FOLLOWLOCATION => true]);
    $content = curl_exec($ch);
    curl_close($ch);
    return $content;
}
?>