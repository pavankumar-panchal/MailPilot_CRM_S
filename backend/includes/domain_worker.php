<?php
$mysqli = new mysqli("127.0.0.1", "root", "", "CRM");
if ($mysqli->connect_error) exit(1);

$start_id = $argv[1] ?? 0;
$end_id = $argv[2] ?? 0;

$query = "SELECT id, sp_domain FROM emails WHERE id BETWEEN $start_id AND $end_id AND domain_verified = 0";
$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $domain = $row['sp_domain'];
    $ip = null;

    // Try MX
    $dns_records = dns_get_record($domain, DNS_MX);
    if (!empty($dns_records)) {
        usort($dns_records, fn($a, $b) => $a['pri'] <=> $b['pri']);
        $target = $dns_records[0]['target'] ?? '';
        if ($target) {
            $resolved = gethostbyname($target);
            if (filter_var($resolved, FILTER_VALIDATE_IP)) {
                $ip = $resolved;
            }
        }
    }

    // Fallback to A record
    if (!$ip) {
        $aRecord = gethostbyname($domain);
        if (filter_var($aRecord, FILTER_VALIDATE_IP)) {
            $ip = $aRecord;
        }
    }

    $status = $ip ? 1 : 0;
    $response = $ip ?: "Invalid domain";

    $stmt = $mysqli->prepare("UPDATE emails SET domain_verified = 1, domain_status = ?, validation_response = ? WHERE id = ?");
    $stmt->bind_param("isi", $status, $response, $id);
    $stmt->execute();
    $stmt->close();
}
$mysqli->close();