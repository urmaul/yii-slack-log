<?php

namespace urmaul\yii\log\slack;

use Yii;
use HttpClient;

/**
 * Log target that pushes logs to Slack channel.
 */
class Target extends \CLogRoute
{
    /**
     * Value "Webhook URL" from slack.
     * @var string
     */
    public $webhookUrl;
    
    /**
     * Bot username. Defaults to application name.
     * @var string
     */
    public $username = null;
    /**
     * Bot icon emoji
     * @var string 
     */
    public $emoji = ':beetle:';
    
    /**
     * Message prefix
     * @var string 
     */
    public $prefix;
    
    protected $messages;
    
    /**
     * Initializes the route.
     * This method is invoked after the route is created by the route manager.
     */
    public function init()
    {
        parent::init();
        
        if (!$this->webhookUrl)
            throw new InvalidConfigException("Unable to append to log file: {$this->logFile}");
        
        if (!$this->username)
            $this->username = Yii::app()->name;
    }
    
    /**
     * Pushes log messages to slack.
     */
    public function processLogs($logs)
    {
        $this->messages = $logs;
        
        list($text, $attachments) = $this->formatMessages();
        
        $body = json_encode([
            'username' => $this->username,
            'icon_emoji' => $this->emoji,
            'text' => $text,
            'attachments' => $attachments,
        ], JSON_PRETTY_PRINT);
        
        $params = ['headers' => ['Content-Type: application/json']];
        HttpClient::from()->post($this->webhookUrl, $body, $params);
    }
    
    /**
     * Formats all log messages as one big slach message
     * @return array [$text, $attachments]
     */
    protected function formatMessages()
    {
        $text = ($this->prefix ? $this->prefix . "\n" : '');
        $attachments = [];
        
        try {
            $currentUrl = Yii::app()->request->hostInfo . Yii::app()->request->requestUri;
            $text .= '>Current URL: <' . $currentUrl . '>' . "\n";
            
            $attachmentLink = ['title_link' => $currentUrl];
        } catch (\Exception $exc) {}
        
        foreach ($this->messages as $message) {
            if (is_string($message[0]) && $message[1] === \CLogger::LEVEL_INFO) {
                $attachments[] = [
                    'fallback' => $message[0],
                    'text' => $message[0],
                    'color' => '#439FE0',
                ];
                
            } elseif ($message[1] === \CLogger::LEVEL_ERROR) {
                $title = preg_match("/with message '([^']*)' in/", $message[0], $match) ? $match[1] : null;
                $attachments[] = [
                    'fallback' => $message[0],
                    'title' => $title,
                    'text' => $message[0],
                    'color' => 'danger',
                    'mrkdwn_in' => ['text'],
                ] + $attachmentLink;
                
            } else {
                $text .= $this->formatMessage($message) . "\n";
            }
        }
        
        return [$text, $attachments];
    }
}
