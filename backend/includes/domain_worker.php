<?php
    $conn = new mysqli("127.0.0.1", "root", "", "CRM");
    if ($conn->connect_error) exit(1);

    $start_id = $argv[1] ?? 0;
    $end_id = $argv[2] ?? 0;

    $query = "SELECT id, sp_domain FROM emails WHERE id BETWEEN $start_id AND $end_id AND domain_verified = 0";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        $domain = $row["sp_domain"];
        $ip = false;

        if (getmxrr($domain, $mxhosts)) {
            $mxIp = gethostbyname($mxhosts[0]);
            if (filter_var($mxIp, FILTER_VALIDATE_IP)) $ip = $mxIp;
        }

        if (!$ip) {
            $aRecord = gethostbyname($domain);
            if (filter_var($aRecord, FILTER_VALIDATE_IP)) $ip = $aRecord;
        }

        $status = $ip ? 1 : 0;
        $response = $ip ? $ip : "Invalid domain";

        $update = $conn->prepare("UPDATE emails SET domain_verified = 1, domain_status = ?, validation_response = ? WHERE id = ?");
        $update->bind_param("isi", $status, $response, $row["id"]);
        $update->execute();
        $update->close();
    }
    $conn->close();
    ?>