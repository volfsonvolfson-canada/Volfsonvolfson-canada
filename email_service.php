<?php
/**
 * Email Service
 * Сервис для отправки email через Mailgun
 */

require_once 'config.php';
require_once 'common.php';

/**
 * Отправка email через Mailgun API
 * 
 * @param string $to Email получателя
 * @param string $subject Тема письма
 * @param string $htmlContent HTML содержимое письма
 * @param string $textContent Текстовое содержимое письма (опционально)
 * @return array Результат отправки
 */
function sendEmail($to, $subject, $htmlContent, $textContent = '') {
    try {
        // Проверяем, что Mailgun настроен
        if (empty(MAILGUN_API_KEY)) {
            error_log("Mailgun API key is not configured");
            return [
                'success' => false,
                'error' => 'Mailgun API key is not configured'
            ];
        }
        
        if (empty(MAILGUN_DOMAIN)) {
            error_log("Mailgun domain is not configured");
            return [
                'success' => false,
                'error' => 'Mailgun domain is not configured'
            ];
        }
        
        // Проверяем валидность email
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Invalid email address'
            ];
        }
        
        // Если текстового контента нет, создаем простую версию из HTML
        if (empty($textContent)) {
            $textContent = strip_tags($htmlContent);
        }
        
        // URL Mailgun API
        $url = 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages';
        
        // Данные для отправки (Mailgun использует form-data)
        $data = [
            'from' => MAILGUN_FROM_NAME . ' <' . MAILGUN_FROM_EMAIL . '>',
            'to' => $to,
            'subject' => $subject,
            'text' => $textContent,
            'html' => $htmlContent
        ];
        
        // Отправляем запрос к Mailgun API
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        // Mailgun использует HTTP Basic Authentication
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . MAILGUN_API_KEY);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        
        // Логируем попытку отправки для отладки
        error_log("Mailgun: Attempting to send email to {$to} via domain " . MAILGUN_DOMAIN);
        error_log("Mailgun: URL: {$url}");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Логируем ответ для отладки
        error_log("Mailgun: HTTP Code: {$httpCode}");
        error_log("Mailgun: Response: " . substr($response, 0, 500));
        if ($curlError) {
            error_log("Mailgun: cURL Error: {$curlError}");
        }
        
        // Проверяем результат
        if ($curlError) {
            throw new Exception("cURL error: {$curlError}");
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            // Успешная отправка
            $responseData = json_decode($response, true);
            $messageId = $responseData['id'] ?? 'unknown';
            logActivity("Email sent successfully: To {$to}, Subject: {$subject}, Message ID: {$messageId}");
            error_log("Mailgun: Email sent successfully. Message ID: {$messageId}");
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'message_id' => $messageId
            ];
        } else {
            // Ошибка отправки
            $error = json_decode($response, true);
            $errorMessage = $error['message'] ?? ($response ? substr($response, 0, 200) : 'Unknown error');
            error_log("Mailgun: API error ({$httpCode}): {$errorMessage}");
            throw new Exception("Mailgun API error ({$httpCode}): {$errorMessage}");
        }
        
    } catch (Exception $e) {
        error_log("Send email error: " . $e->getMessage());
        logActivity("Email send failed: To {$to}, Subject: {$subject}, Error: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Отправка подтверждения бронирования гостю
 * 
 * @param array $booking Данные бронирования
 * @return array Результат отправки
 */
function sendBookingConfirmation($booking) {
    try {
        $email = $booking['email'] ?? '';
        $bookingId = $booking['id'] ?? '';
        $confirmationCode = $booking['confirmation_code'] ?? '';
        
        if (empty($email)) {
            throw new Exception('Guest email is required');
        }
        
        // Загружаем шаблон письма
        $htmlContent = loadEmailTemplate('booking_confirmation', [
            'booking' => $booking,
            'confirmation_code' => $confirmationCode,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode($confirmationCode),
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // Если шаблон не найден, используем простой формат
            $htmlContent = generateSimpleBookingConfirmationEmail($booking, $confirmationCode);
        }
        
        $subject = 'Booking Confirmation - Back to Base Hotel';
        
        // Отправляем письмо
        $result = sendEmail($email, $subject, $htmlContent);
        
        // Обновляем время отправки email в базе данных
        if ($result['success'] && $bookingId) {
            global $conn;
            updateRecord($conn, 'booking_confirmations', 
                ['email_sent_at' => date('Y-m-d H:i:s')], 
                'booking_id = ?', 
                [$bookingId]
            );
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Send booking confirmation error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Отправка уведомления о новом запросе на бронирование хозяину
 * 
 * @param array $booking Данные бронирования
 * @return array Результат отправки
 */
function sendBookingRequestToHost($booking) {
    try {
        $hostEmail = MAILGUN_HOST_EMAIL ?? '';
        
        if (empty($hostEmail)) {
            // Если email хозяина не настроен, просто логируем
            logActivity("Host email not configured, skipping booking request notification", 'INFO');
            return [
                'success' => true,
                'message' => 'Host email not configured, notification skipped'
            ];
        }
        
        // Загружаем шаблон письма
        $htmlContent = loadEmailTemplate('booking_request', [
            'booking' => $booking,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // Если шаблон не найден, используем простой формат
            $htmlContent = generateSimpleBookingRequestEmail($booking);
        }
        
        $subject = 'New Booking Request - Back to Base Hotel';
        
        // Отправляем письмо
        return sendEmail($hostEmail, $subject, $htmlContent);
        
    } catch (Exception $e) {
        error_log("Send booking request to host error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Отправка подтверждения бронирования после одобрения хозяином
 * 
 * @param array $booking Данные бронирования
 * @return array Результат отправки
 */
function sendBookingConfirmedToGuest($booking) {
    try {
        $email = $booking['email'] ?? '';
        
        if (empty($email)) {
            throw new Exception('Guest email is required');
        }
        
        // Загружаем шаблон письма
        $htmlContent = loadEmailTemplate('booking_confirmed', [
            'booking' => $booking,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode($booking['confirmation_code'] ?? ''),
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // Если шаблон не найден, используем простой формат
            $htmlContent = generateSimpleBookingConfirmedEmail($booking);
        }
        
        $subject = 'Your Booking Has Been Confirmed - Back to Base Hotel';
        
        // Отправляем письмо
        return sendEmail($email, $subject, $htmlContent);
        
    } catch (Exception $e) {
        error_log("Send booking confirmed to guest error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Отправка уведомления об отмене бронирования
 * 
 * @param array $booking Данные бронирования
 * @param string $reason Причина отмены (опционально)
 * @return array Результат отправки
 */
function sendBookingCancelled($booking, $reason = '') {
    try {
        $email = $booking['email'] ?? '';
        
        if (empty($email)) {
            throw new Exception('Guest email is required');
        }
        
        // Загружаем шаблон письма
        $htmlContent = loadEmailTemplate('booking_cancelled', [
            'booking' => $booking,
            'reason' => $reason,
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // Если шаблон не найден, используем простой формат
            $htmlContent = generateSimpleBookingCancelledEmail($booking, $reason);
        }
        
        $subject = 'Booking Cancellation - Back to Base Hotel';
        
        // Отправляем письмо
        return sendEmail($email, $subject, $htmlContent);
        
    } catch (Exception $e) {
        error_log("Send booking cancelled error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Загрузка email шаблона
 * 
 * @param string $templateName Имя шаблона (без расширения)
 * @param array $variables Переменные для подстановки в шаблон
 * @return string|false HTML содержимое шаблона или false если не найден
 */
function loadEmailTemplate($templateName, $variables = []) {
    $templatePath = __DIR__ . '/templates/email_' . $templateName . '.html';
    
    if (!file_exists($templatePath)) {
        return false;
    }
    
    // Читаем шаблон
    $template = file_get_contents($templatePath);
    
    // Заменяем переменные
    foreach ($variables as $key => $value) {
        if (is_array($value)) {
            // Если значение - массив (например, $booking), заменяем его поля
            foreach ($value as $subKey => $subValue) {
                $template = str_replace('{{' . $key . '.' . $subKey . '}}', htmlspecialchars($subValue ?? ''), $template);
            }
        } else {
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value ?? ''), $template);
        }
    }
    
    return $template;
}

/**
 * Генерация простого HTML письма подтверждения бронирования
 */
function generateSimpleBookingConfirmationEmail($booking, $confirmationCode) {
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    $totalAmount = $booking['total_amount'] ?? 0;
    $currency = $booking['currency'] ?? 'CAD';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4a5568; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .code { font-size: 24px; font-weight: bold; color: #4a5568; text-align: center; padding: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Back to Base Hotel</h1>
            </div>
            <div class='content'>
                <h2>Booking Confirmation</h2>
                <p>Dear {$guestName},</p>
                <p>Thank you for your booking! Your reservation has been received.</p>
                
                <div class='booking-details'>
                    <h3>Booking Details</h3>
                    <p><strong>Confirmation Code:</strong></p>
                    <div class='code'>{$confirmationCode}</div>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                    <p><strong>Total Amount:</strong> {$currency} " . number_format($totalAmount, 2) . "</p>
                </div>
                
                <p>Please save this confirmation code for your records.</p>
                <p>We look forward to welcoming you!</p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel</p>
                <p>If you have any questions, please contact us.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Генерация простого HTML письма запроса на бронирование
 */
function generateSimpleBookingRequestEmail($booking) {
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    $email = $booking['email'] ?? '';
    $phone = $booking['phone'] ?? '';
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $guestsCount = $booking['guests_count'] ?? 1;
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #e53e3e; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .button { display: inline-block; padding: 10px 20px; background-color: #4a5568; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>New Booking Request</h1>
            </div>
            <div class='content'>
                <p>You have received a new booking request:</p>
                
                <div class='booking-details'>
                    <h3>Guest Information</h3>
                    <p><strong>Name:</strong> {$guestName}</p>
                    <p><strong>Email:</strong> {$email}</p>
                    <p><strong>Phone:</strong> {$phone}</p>
                    
                    <h3>Booking Details</h3>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                    <p><strong>Guests:</strong> {$guestsCount}</p>
                </div>
                
                <p style='text-align: center; margin-top: 20px;'>
                    <a href='" . ADMIN_BOOKINGS_URL . "' class='button'>View Booking in Admin Panel</a>
                </p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel - Admin Panel</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Генерация простого HTML письма подтверждения хозяином
 */
function generateSimpleBookingConfirmedEmail($booking) {
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    $confirmationCode = $booking['confirmation_code'] ?? '';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #38a169; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Confirmed!</h1>
            </div>
            <div class='content'>
                <h2>Great News!</h2>
                <p>Dear {$guestName},</p>
                <p>Your booking has been confirmed by our team.</p>
                
                <div class='booking-details'>
                    <h3>Booking Details</h3>
                    <p><strong>Confirmation Code:</strong> {$confirmationCode}</p>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                </div>
                
                <p>We look forward to welcoming you!</p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Генерация простого HTML письма об отмене
 */
function generateSimpleBookingCancelledEmail($booking, $reason = '') {
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #e53e3e; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Cancellation</h1>
            </div>
            <div class='content'>
                <p>Dear {$guestName},</p>
                <p>We're sorry to inform you that your booking has been cancelled.</p>
                
                <div class='booking-details'>
                    <h3>Cancelled Booking</h3>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                    " . (!empty($reason) ? "<p><strong>Reason:</strong> {$reason}</p>" : "") . "
                </div>
                
                <p>If you made a payment, a refund will be processed within 5-10 business days.</p>
                <p>We hope to welcome you in the future.</p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

?>

