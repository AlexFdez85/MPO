<?php
// mpo/lib/clientes_upsert.php
function mpo_upsert_cliente_y_vinculo(PDO $pdo, array $data) {
  // $data: razon_social, rfc, email, telefono, domicilio, ciudad, estado, pais, cp,
  //       mayorista_user_id, zona (opcional), exclusivo (0/1), status ('activo' x defecto)

  // 1) CLIENTE (tabla MPO 'clientes'): intenta por RFC o email
  $clienteId = null;
  if (!empty($data['rfc'])) {
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE rfc = :rfc LIMIT 1");
    $stmt->execute([':rfc'=>$data['rfc']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $clienteId = (int)$row['id'];
  }
  if (!$clienteId && !empty($data['email'])) {
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = :email LIMIT 1");
    $stmt->execute([':email'=>$data['email']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $clienteId = (int)$row['id'];
  }
  if (!$clienteId) {
    $ins = $pdo->prepare("INSERT INTO clientes
      (razon_social, rfc, email, telefono, domicilio, ciudad, estado, pais, cp, created_at)
      VALUES (:razon_social,:rfc,:email,:telefono,:domicilio,:ciudad,:estado,:pais,:cp, NOW())");
    $ins->execute([
      ':razon_social'=>$data['razon_social'] ?? '',
      ':rfc'=>$data['rfc'] ?? '',
      ':email'=>$data['email'] ?? '',
      ':telefono'=>$data['telefono'] ?? '',
      ':domicilio'=>$data['domicilio'] ?? '',
      ':ciudad'=>$data['ciudad'] ?? '',
      ':estado'=>$data['estado'] ?? '',
      ':pais'=>$data['pais'] ?? 'México',
      ':cp'=>$data['cp'] ?? ''
    ]);
    $clienteId = (int)$pdo->lastInsertId();
  }

  // 2) VÍNCULO clientes_mayorista
  $status    = $data['status']    ?? 'activo';
  $zona      = $data['zona']      ?? null;
  $exclusivo = (int)($data['exclusivo'] ?? 1);

  $sel = $pdo->prepare("SELECT id FROM clientes_mayorista WHERE cliente_id=:cid AND mayorista_user_id=:uid LIMIT 1");
  $sel->execute([':cid'=>$clienteId, ':uid'=>$data['mayorista_user_id']]);
  $cm = $sel->fetch(PDO::FETCH_ASSOC);

  if ($cm) {
    $upd = $pdo->prepare("UPDATE clientes_mayorista
      SET status=:st, zona=:zona, exclusivo=:ex, updated_at=NOW()
      WHERE id=:id");
    $upd->execute([':st'=>$status, ':zona'=>$zona, ':ex'=>$exclusivo, ':id'=>$cm['id']]);
  } else {
    $ins2 = $pdo->prepare("INSERT INTO clientes_mayorista
      (cliente_id, mayorista_user_id, status, exclusivo, zona, created_at, updated_at)
      VALUES (:cid,:uid,:st,:ex,:zona,NOW(),NOW())");
    $ins2->execute([
      ':cid'=>$clienteId, ':uid'=>$data['mayorista_user_id'],
      ':st'=>$status, ':ex'=>$exclusivo, ':zona'=>$zona
    ]);
  }

  return $clienteId;
}
