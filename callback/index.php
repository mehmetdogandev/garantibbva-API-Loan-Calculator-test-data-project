<?php
class GarantiBBVALoanCalculator
{
    private $baseUrl = 'https://apis.garantibbva.com.tr:443';
    private $clientId;
    private $clientSecret;
    private $accessToken;

    public function __construct($clientId, $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Garanti BBVA API Key sistemini kullan (OAuth gerekmiyor)
     */
    public function getAccessToken()
    {
        $this->accessToken = $this->clientId;
        return $this->accessToken;
    }

    /**
     * Kredi hesaplama - ödeme planı listesi
     */
    public function getPaymentPlanList($loanAmount, $showOnlyBestOptions = false)
    {
        $this->ensureAccessToken();

        $url = $this->baseUrl . '/loans/v1/paymentPlan';

        $data = [
            'loanType' => '2',
            'campaignCode' => 'TESTFIRM',
            'loanAmount' => $loanAmount
        ];

        if ($showOnlyBestOptions) {
            $data['showOnlyBestOptions'] = 'true';
        }

        $headers = [
            'Content-Type: application/json',
            'ApiKey: ' . $this->accessToken
        ];

        return $this->makeRequest($url, 'POST', $data, $headers);
    }

    /**
     * Kredi hesaplama - detaylı ödeme planı
     */
    public function getDetailedPaymentPlan($loanAmount, $dueNum)
    {
        $this->ensureAccessToken();

        $url = $this->baseUrl . '/loans/v1/paymentPlan';

        $data = [
            'loanType' => '2',
            'campaignCode' => 'TESTFIRM',
            'loanAmount' => $loanAmount,
            'dueNum' => $dueNum
        ];

        $headers = [
            'Content-Type: application/json',
            'ApiKey: ' . $this->accessToken
        ];

        return $this->makeRequest($url, 'POST', $data, $headers);
    }

    /**
     * Access token kontrolü
     */
    private function ensureAccessToken()
    {
        if (!$this->accessToken) {
            $this->getAccessToken();
        }
    }

    /**
     * HTTP request yapma
     */
    private function makeRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        if ($data && $method === 'POST') {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        if (empty($response)) {
            throw new Exception('Empty reply from server');
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception('HTTP Error ' . $httpCode . ': ' . $response);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            // JSON hatası varsa raw response'u düzeltmeye çalış
            $response = str_replace(',9118,', '.9118,', $response);
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['raw_response' => $response];
            }
        }

        return $decodedResponse;
    }
}

// API kullanımı
try {
    $clientId = 'YOUR_CLIENT_ID'; // API Key
    $clientSecret = 'YOUR_CLIENT_SECRET'; // API Secret

    $loanCalculator = new GarantiBBVALoanCalculator($clientId, $clientSecret);

    $result = null;
    $error = null;
    $actionType = '';
    $loanAmount = '';

    if ($_POST) {
        $loanAmount = $_POST['loanAmount'] ?? '';
        $actionType = $_POST['action'] ?? '';

        if ($loanAmount && is_numeric($loanAmount)) {
            switch ($actionType) {
                case 'paymentPlan':
                    $result = $loanCalculator->getPaymentPlanList($loanAmount);
                    break;
                case 'bestOptions':
                    $result = $loanCalculator->getPaymentPlanList($loanAmount, true);
                    break;
                case 'detailedPlan':
                    $dueNum = $_POST['dueNum'] ?? '';
                    if ($dueNum && is_numeric($dueNum)) {
                        $result = $loanCalculator->getDetailedPaymentPlan($loanAmount, $dueNum);
                    } else {
                        $error = 'Taksit sayısı gerekli!';
                    }
                    break;
            }
        } else {
            $error = 'Geçerli bir kredi tutarı giriniz!';
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Helper fonksiyonlar
function hasData($result, $key)
{
    return isset($result['data'][$key]) && !empty($result['data'][$key]);
}

function getData($result, $key, $default = [])
{
    return $result['data'][$key] ?? $default;
}

function getNestedData($result, $keys, $default = null)
{
    $current = $result;
    foreach ($keys as $key) {
        if (!isset($current[$key])) {
            return $default;
        }
        $current = $current[$key];
    }
    return $current;
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏦 Garanti BBVA Kredi Hesaplayıcı</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 3rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            color: #666;
            font-size: 1.2rem;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-group {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid #667eea;
        }

        .form-group h3 {
            color: #1e3c72;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
        }

        .btn-group {
            text-align: center;
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 18px 35px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .alert {
            padding: 20px;
            margin: 20px 0;
            border-radius: 12px;
            font-weight: 500;
            border: none;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        .dashboard-section {
            display: none;
            animation: fadeIn 0.8s ease-in;
        }

        .dashboard-section.active {
            display: block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: block;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 500;
        }

        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 25px;
            text-align: center;
        }

        .result-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .result-table th {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .result-table td {
            padding: 15px;
            border-bottom: 1px solid #e8ecf0;
            font-size: 0.9rem;
        }

        .result-table tr:nth-child(even) {
            background: #f8f9fa;
        }

        .result-table tr:hover {
            background: #e3f2fd;
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .amount {
            font-weight: 600;
            color: #1e3c72;
        }

        .rate {
            color: #f44336;
            font-weight: 600;
        }

        .best-option {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 20%) !important;
            color: white;
            font-weight: 600;
        }

        .best-option td {
            color: white;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 20px;
            text-align: center;
        }

        .comparison-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .comparison-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .comparison-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .comparison-card h4 {
            color: #1e3c72;
            margin-bottom: 10px;
        }

        .comparison-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2a5298;
        }

        .error-message {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            color: #856404;
        }

        @media (max-width: 968px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 10px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .btn-group {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <h1>🏦 Garanti BBVA Kredi Hesaplayıcı</h1>
            <p>API ile kredi hesaplama ve ödeme planı oluşturma</p>
        </header>

        <div class="form-section">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <h3>💰 Kredi Tutarı</h3>
                        <label for="loanAmount">Kredi Tutarı (TL):</label>
                        <input type="number" id="loanAmount" name="loanAmount"
                            value="<?= htmlspecialchars($_POST['loanAmount'] ?? '50000') ?>"
                            min="1000" max="1000000" step="1000" required>
                    </div>

                    <div class="form-group">
                        <h3>📊 Detaylı Plan için</h3>
                        <label for="dueNum">Taksit Sayısı:</label>
                        <input type="number" id="dueNum" name="dueNum"
                            value="<?= htmlspecialchars($_POST['dueNum'] ?? '12') ?>"
                            min="1" max="60" step="1">
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" name="action" value="paymentPlan" class="btn btn-primary">
                        📋 Ödeme Planı Listesi
                    </button>
                    <button type="submit" name="action" value="bestOptions" class="btn btn-success">
                        ⭐ En İyi Seçenekler
                    </button>
                    <button type="submit" name="action" value="detailedPlan" class="btn btn-info">
                        🔍 Detaylı Plan
                    </button>
                </div>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>❌ Hata:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($result && isset($result['data'])): ?>
            <div class="dashboard-section active">
            <?php endif; ?>

            <?php if ($actionType === 'paymentPlan' && hasData($result, 'list')): ?>
                <?php $planList = getData($result, 'list'); ?>
                <!-- İstatistik Kartları -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">💰</span>
                        <div class="stat-value"><?= number_format($loanAmount) ?></div>
                        <div class="stat-label">Kredi Tutarı (TL)</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">📊</span>
                        <div class="stat-value"><?= count($planList) ?></div>
                        <div class="stat-label">Toplam Seçenek</div>
                    </div>
                    <?php if (!empty($planList)): ?>
                        <div class="stat-card">
                            <span class="stat-icon">⭐</span>
                            <div class="stat-value"><?= number_format(end($planList)['annualCostRate'] ?? 0, 2) ?>%</div>
                            <div class="stat-label">En Düşük Yıllık Maliyet</div>
                        </div>
                        <div class="stat-card pulse">
                            <span class="stat-icon">🎯</span>
                            <div class="stat-value"><?= number_format(end($planList)['installmentAmount'] ?? 0, 2) ?></div>
                            <div class="stat-label">En Düşük Aylık Ödeme (TL)</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Grafikler -->
                <div class="chart-grid">
                    <div class="chart-container">
                        <h3 class="chart-title">💹 Aylık Taksit vs Taksit Sayısı</h3>
                        <canvas id="paymentChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3 class="chart-title">📈 Yıllık Maliyet Oranları</h3>
                        <canvas id="costChart"></canvas>
                    </div>
                </div>

                <!-- Tablo -->
                <div class="table-container">
                    <h2 class="table-title">📋 Tüm Ödeme Planı Seçenekleri</h2>
                    <?php if (!empty($planList)): ?>
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <th>Taksit Sayısı</th>
                                    <th>Aylık Ödeme</th>
                                    <th>Toplam Tutar</th>
                                    <th>Faiz Oranı</th>
                                    <th>Yıllık Maliyet</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planList as $index => $plan): ?>
                                    <tr <?= $index === count($planList) - 1 ? 'class="best-option"' : '' ?>>
                                        <td><strong><?= $plan['dueNum'] ?? 'N/A' ?> Ay</strong></td>
                                        <td class="amount"><?= number_format($plan['installmentAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="amount"><?= number_format($plan['totalInstallmentAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="rate"><?= number_format($plan['monthlyInterestRate'] ?? 0, 2) ?>%</td>
                                        <td class="rate"><?= number_format($plan['annualCostRate'] ?? 0, 2) ?>%</td>
                                        <td>
                                            <?php
                                            $dueNum = $plan['dueNum'] ?? 0;
                                            echo $dueNum <= 6 ? '🚀 Hızlı' : ($dueNum <= 24 ? '⚖️ Dengeli' : '💰 Ekonomik');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="error-message">
                            ⚠️ Ödeme planı listesi bulunamadı. API yanıtını kontrol edin.
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($actionType === 'bestOptions'): ?>
                <?php
                $bestLoan = getNestedData($result, ['data', 'bestLoanOption']);
                $bestInstallment = getNestedData($result, ['data', 'bestInstallmentOption']);
                ?>

                <?php if ($bestLoan || $bestInstallment): ?>
                    <!-- En İyi Seçenekler -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-icon">💰</span>
                            <div class="stat-value"><?= number_format($loanAmount) ?></div>
                            <div class="stat-label">Kredi Tutarı (TL)</div>
                        </div>
                        <?php if ($bestLoan): ?>
                            <div class="stat-card pulse">
                                <span class="stat-icon">⭐</span>
                                <div class="stat-value"><?= number_format($bestLoan['installmentAmount'] ?? 0, 2) ?></div>
                                <div class="stat-label">En İyi Aylık Ödeme (TL)</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-container">
                        <h2 class="table-title">⭐ En İyi Seçenekler</h2>

                        <?php if ($bestLoan && $bestInstallment): ?>
                            <div class="comparison-cards">
                                <div class="comparison-card">
                                    <h4>💡 En Düşük Aylık Ödeme</h4>
                                    <div class="value"><?= number_format($bestLoan['installmentAmount'] ?? 0, 2) ?> TL</div>
                                    <small><?= $bestLoan['dueNum'] ?? 'N/A' ?> Taksit</small>
                                </div>
                                <div class="comparison-card">
                                    <h4>🚀 En Hızlı Ödeme</h4>
                                    <div class="value"><?= number_format($bestInstallment['installmentAmount'] ?? 0, 2) ?> TL</div>
                                    <small><?= $bestInstallment['dueNum'] ?? 'N/A' ?> Taksit</small>
                                </div>
                            </div>

                            <table class="result-table">
                                <thead>
                                    <tr>
                                        <th>Seçenek</th>
                                        <th>Taksit Sayısı</th>
                                        <th>Aylık Ödeme</th>
                                        <th>Toplam Tutar</th>
                                        <th>Yıllık Maliyet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="best-option">
                                        <td><strong>💰 En Ekonomik</strong></td>
                                        <td><?= $bestLoan['dueNum'] ?? 'N/A' ?> Ay</td>
                                        <td class="amount"><?= number_format($bestLoan['installmentAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="amount"><?= number_format($bestLoan['totalInstallmentAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="rate"><?= number_format($bestLoan['annualCostRate'] ?? 0, 2) ?>%</td>
                                    </tr>
                                    <tr>
                                        <td><strong>🚀 En Hızlı</strong></td>
                                        <td><?= $bestInstallment['dueNum'] ?? 'N/A' ?> Ay</td>
                                        <td class="amount"><?= number_format($bestInstallment['installmentAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="amount"><?= number_format($bestInstallment['totalInstallmentAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="rate"><?= number_format($bestInstallment['annualCostRate'] ?? 0, 2) ?>%</td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="error-message">
                                ⚠️ En iyi seçenekler bulunamadı. API yanıtını kontrol edin.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        ⚠️ En iyi seçenekler verisi bulunamadı.
                    </div>
                <?php endif; ?>

            <?php elseif ($actionType === 'detailedPlan'): ?>
                <?php
                $installments = getNestedData($result, ['data', 'installments'], []);
                $totalInfo = getNestedData($result, ['data', 'total']);
                ?>

                <!-- Detaylı Plan -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">💰</span>
                        <div class="stat-value"><?= number_format($loanAmount) ?></div>
                        <div class="stat-label">Kredi Tutarı (TL)</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">📊</span>
                        <div class="stat-value"><?= count($installments) ?></div>
                        <div class="stat-label">Taksit Sayısı</div>
                    </div>
                    <?php if ($totalInfo): ?>
                        <div class="stat-card">
                            <span class="stat-icon">💳</span>
                            <div class="stat-value"><?= number_format($totalInfo['installmentAmount'] ?? 0, 2) ?></div>
                            <div class="stat-label">Aylık Ödeme (TL)</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <h2 class="table-title">🔍 Detaylı Ödeme Planı</h2>
                    <?php if (!empty($installments)): ?>
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <th>Taksit No</th>
                                    <th>Tarih</th>
                                    <th>Taksit Tutarı</th>
                                    <th>Ana Para</th>
                                    <th>Faiz</th>
                                    <th>Kalan Ana Para</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($installments as $index => $installment): ?>
                                    <tr>
                                        <td><strong><?= $index + 1 ?></strong></td>
                                        <td><?= $installment['paymentDate'] ?? 'N/A' ?></td>
                                        <td class="amount"><?= number_format($installment['installmentAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="amount"><?= number_format($installment['capitalAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="rate"><?= number_format($installment['interestAmount'] ?? 0, 2) ?> TL</td>
                                        <td class="amount"><?= number_format($installment['remainingCapitalAmount'] ?? 0, 2) ?> TL</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="error-message">
                            ⚠️ Detaylı ödeme planı bulunamadı. API yanıtını kontrol edin.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Ham API Yanıtı -->
            <div class="table-container" style="margin-top: 30px;">
                <details>
                    <summary style="cursor: pointer; padding: 15px; background: #f8f9fa; border-radius: 10px; margin-bottom: 15px;">
                        <strong>🔍 Ham API Yanıtını Görüntüle</strong>
                    </summary>
                    <pre style="background: #f8f9fa; padding: 20px; border-radius: 10px; overflow-x: auto; font-size: 0.9rem;"><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
            </div>
            </div>
            <?php if ($actionType === 'paymentPlan' && hasData($result, 'list')): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Verinin mevcut olduğunu kontrol et
                        const loanData = <?= json_encode(getData($result, 'list')) ?>;

                        if (!loanData || loanData.length === 0) {
                            console.log('Grafik verisi bulunamadı');
                            return;
                        }

                        // Aylık Ödeme Grafiği
                        const paymentCanvas = document.getElementById('paymentChart');
                        const costCanvas = document.getElementById('costChart');

                        if (paymentCanvas && typeof Chart !== 'undefined') {
                            const paymentCtx = paymentCanvas.getContext('2d');
                            new Chart(paymentCtx, {
                                type: 'line',
                                data: {
                                    labels: loanData.map(d => (d.dueNum || 0) + ' Ay'),
                                    datasets: [{
                                        label: 'Aylık Ödeme (TL)',
                                        data: loanData.map(d => d.installmentAmount || 0),
                                        borderColor: '#667eea',
                                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.4,
                                        pointBackgroundColor: '#667eea',
                                        pointBorderColor: '#fff',
                                        pointBorderWidth: 3,
                                        pointRadius: 6
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                color: 'rgba(0,0,0,0.1)'
                                            }
                                        },
                                        x: {
                                            grid: {
                                                color: 'rgba(0,0,0,0.1)'
                                            }
                                        }
                                    }
                                }
                            });
                        }

                        // Maliyet Oranı Grafiği
                        if (costCanvas && typeof Chart !== 'undefined') {
                            const costCtx = costCanvas.getContext('2d');
                            new Chart(costCtx, {
                                type: 'bar',
                                data: {
                                    labels: loanData.map(d => (d.dueNum || 0) + ' Ay'),
                                    datasets: [{
                                        label: 'Yıllık Maliyet (%)',
                                        data: loanData.map(d => d.annualCostRate || 0),
                                        backgroundColor: loanData.map((d, i) =>
                                            `hsl(${240 - (i * 3)}, 70%, 60%)`
                                        ),
                                        borderColor: loanData.map((d, i) =>
                                            `hsl(${240 - (i * 3)}, 70%, 50%)`
                                        ),
                                        borderWidth: 2,
                                        borderRadius: 8
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                color: 'rgba(0,0,0,0.1)'
                                            }
                                        },
                                        x: {
                                            grid: {
                                                color: 'rgba(0,0,0,0.1)'
                                            }
                                        }
                                    }
                                }
                            });
                        }

                        // Animasyonlu sayılar
                        setTimeout(() => {
                            const statValues = document.querySelectorAll('.stat-value');
                            statValues.forEach(stat => {
                                const text = stat.textContent;
                                const finalValue = parseFloat(text.replace(/[^0-9.]/g, ''));
                                if (!isNaN(finalValue)) {
                                    let currentValue = 0;
                                    const increment = finalValue / 30;

                                    const timer = setInterval(() => {
                                        currentValue += increment;
                                        if (currentValue >= finalValue) {
                                            currentValue = finalValue;
                                            clearInterval(timer);
                                        }

                                        if (text.includes('%')) {
                                            stat.textContent = currentValue.toFixed(2) + '%';
                                        } else if (text.includes(',')) {
                                            stat.textContent = Math.floor(currentValue).toLocaleString('tr-TR');
                                        } else {
                                            stat.textContent = currentValue.toFixed(2);
                                        }
                                    }, 50);
                                }
                            });
                        }, 500);

                        // Hover efektleri
                        // Hover efektleri
                        document.querySelectorAll('.result-table tr').forEach(row => {
                            row.addEventListener('mouseenter', () => {
                                row.style.transform = 'scale(1.02)';
                            });
                            row.addEventListener('mouseleave', () => {
                                row.style.transform = 'scale(1)';
                            });
                        });
                    });
                </script>
            <?php endif; ?>

    </div> <!-- container div'inin kapanışı -->
</body>

</html>