# yii-slack-log

Yii log route that pushes logs to Slack channel. This is a fork of [yii2-slack-log](https://github.com/urmaul/yii2-slack-log) modified to work in Yii 1.1.

## How to install

1. Add "Incoming WebHook" to slack.
2. Attach log route.
    
    ```
    composer require urmaul/yii-slack-log '~1.0'
    ```
    
3. Add this route to log targets.
    
    ```
    'log' => [
        'class'=>'CLogRouter',
        'routes' => [
            [
                'class' => 'urmaul\yii\log\slack\Target',
                'levels' => 'error', // Send message on errors
                'except' => 'exception.CHttpException.404', // ...except 404
                'enabled' => !YII_DEBUG, // No not send in debug mode
                'webhookUrl' => 'YOUR_WEBHOOK_URL_FROM_SLACK',
                //'username' => 'MYBOT', // Bot username. Defaults to app name
                //'icon_url' => null, // Bot icon URL
                //'icon_emoji' => ':beetle:', // Bot icon emoji
                //'prefix' => '', // Any text prefix. As a sample, you can mention @yourself.
            ],
        ],
    ],
    ```
