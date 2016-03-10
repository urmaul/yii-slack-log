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
     * Bot icon url
     * @var string 
     */
    public $icon_url = null;
    /**
     * Bot icon emoji
     * @var string 
     */
    public $icon_emoji = ':beetle:';
    
    /**
     * Message prefix
     * @var string 
     */
    public $prefix;
    
    /**
     * @var array list of the PHP predefined variables that should be logged in a message.
     * Note that a variable must be accessible via `$GLOBALS`. Otherwise it won't be logged.
     * Defaults to `['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER']`.
     */
    public $logVars = ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'];
    
    protected $messages;
    
    /**
     * Initializes the route.
     * This method is invoked after the route is created by the route manager.
     */
    public function init()
    {
        parent::init();
        
        if (!$this->webhookUrl)
            $this->enabled = false;
        
        if (!$this->username)
            $this->username = Yii::app()->name;
        
        // Not pushing Slackbot request errors to slack.
        if (Yii::app()->request && preg_match('/^Slackbot-/', Yii::app()->request->userAgent))
            $this->enabled = false;
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
            'icon_url' => $this->icon_url,
            'icon_emoji' => $this->icon_emoji,
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
        
        // Add logVars dump
        if ($this->logVars) {
            $this->messages[] = [$this->getContextMessage(), \CLogger::LEVEL_INFO];
        }

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

    /**
     * Generates the context information to be logged.
     * The default implementation will dump user information, system variables, etc.
     * Copypasted from https://github.com/yiisoft/yii2/blob/master/framework/log/Target.php
     * @return string the context information. If an empty string, it means no context information.
     */
    protected function getContextMessage()
    {
        $context = [];
        foreach ($this->logVars as $name) {
            if (!empty($GLOBALS[$name])) {
                $context[] = "\${$name} = " . var_export($GLOBALS[$name], true);
            }
        }
        return implode("\n\n", $context);
    }
}
