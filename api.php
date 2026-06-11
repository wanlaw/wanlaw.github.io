<?php
// api.php — 초간단 링크 게시판 API (JSON 파일 저장)
// 같은 폴더에 index.html과 두고, 웹서버(http/https)로 접속하세요.
// 중요: 관리자 암호를 반드시 변경하세요.
$ADMIN_PASSWORD = 'CHANGE_ME_ADMIN_PASSWORD';

// CORS/JSON 헤더
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// 저장소 경로 준비
$dir = __DIR__ . '/data';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$file = $dir . '/links.json';

// 데이터 로드/세이브
function load_data($file) {
  if (!file_exists($file)) return ['next_id'=>1, 'items'=>[]];
  $raw = @file_get_contents($file);
  if ($raw === false) return ['next_id'=>1, 'items'=>[]];
  $d = json_decode($raw, true);
  if (!is_array($d) || !isset($d['items'])) return ['next_id'=>1, 'items'=>[]];
  return $d;
}
function save_data($file, $data) {
  $ok = @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  if ($ok === false) throw new Exception('저장 실패');
}

// 입력 JSON
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

try {
  $data = load_data($file);
  $items = $data['items'];
  $next_id = intval($data['next_id'] ?? 1);

  if ($action === 'list') {
    // order(순서) 기준 정렬
    usort($items, function($a,$b){ return intval($a['order']??0) <=> intval($b['order']??0); });
    echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($action === 'add') {
    $title = trim($input['title'] ?? '');
    $url = trim($input['url'] ?? '');
    $pass = (string)($input['admin_password'] ?? '');
    if ($pass !== $ADMIN_PASSWORD) { http_response_code(401); echo json_encode(['error'=>'인증 실패']); exit; }
    if ($title === '' || $url === '') { http_response_code(400); echo json_encode(['error'=>'제목/URL 필수']); exit; }
    // 새 아이템
    $id = $next_id++;
    $order = count($items) ? max(array_map(fn($it)=>intval($it['order']??0), $items)) + 1 : 1;
    $items[] = [
      'id'=>$id, 'title'=>$title, 'url'=>$url,
      'created_at'=>time(), 'order'=>$order
    ];
    save_data($file, ['next_id'=>$next_id, 'items'=>$items]);
    echo json_encode(['ok'=>true, 'id'=>$id], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($action === 'delete') {
    $id = intval($input['id'] ?? 0);
    $pass = (string)($input['admin_password'] ?? '');
    if ($pass !== $ADMIN_PASSWORD) { http_response_code(401); echo json_encode(['error'=>'인증 실패']); exit; }
    $before = count($items);
    $items = array_values(array_filter($items, fn($it)=>intval($it['id'])!==$id));
    if (count($items) === $before) { http_response_code(404); echo json_encode(['error'=>'존재하지 않는 항목']); exit; }
    save_data($file, ['next_id'=>$next_id, 'items'=>$items]);
    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($action === 'reorder') {
    $order_ids = $input['order'] ?? [];
    $pass = (string)($input['admin_password'] ?? '');
    if ($pass !== $ADMIN_PASSWORD) { http_response_code(401); echo json_encode(['error'=>'인증 실패']); exit; }
    // id 순서대로 order 값을 재부여
    $map = [];
    $pos = 1;
    foreach ($order_ids as $id) { $map[intval($id)] = $pos++; }
    foreach ($items as &$it) {
      $id = intval($it['id']);
      if (isset($map[$id])) { $it['order'] = $map[$id]; }
    }
    unset($it);
    save_data($file, ['next_id'=>$next_id, 'items'=>$items]);
    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
  }

  // 도움말
  echo json_encode([
    'endpoints'=>[
      'GET  ?action=list',
      'POST ?action=add     body:{title,url,admin_password}',
      'POST ?action=delete  body:{id,admin_password}',
      'POST ?action=reorder body:{order:[id1,id2,...],admin_password}'
    ],
    'status'=>'ok'
  ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error'=>'서버 오류','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}