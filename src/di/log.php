<?php

use Monolog\Logger as Monlogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\SlackWebhookHandler;
use gh_rboliveira\TelegramHandler\TelegramHandler;
use Monolog\Handler\SwiftMailerHandler;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\NormalizerFormatter;
use pahanini\Monolog\Formatter\CliFormatter;
use Monolog\Formatter\HtmlFormatter;

use Monolog\Processor\WebProcessor;
use Monolog\Processor\MemoryUsageProcessor;

return function(string $workerName = null) {
    $messages = [];

    if (getenv('LOGGER_SYSLOG_LEVEL' )) {
        $syslog = new SyslogHandler(gethostname(), LOG_USER, Monlogger::toMonologLevel( getenv('LOGGER_SYSLOG_LEVEL' )) );
        $syslog->setFormatter(new LineFormatter($_ENV['LOGGER_SYSLOG_FORMAT'] ?? '%level_name%: %message%' ));
    } else {
        $syslog = null;
    }

    if (getenv('LOGGER_STDOUT_LEVEL' )) {
        $stdout = new StreamHandler(getenv('LOGGER_STDOUT_FILE' ), Monlogger::toMonologLevel( getenv('LOGGER_STDOUT_LEVEL' )) );
        if (getenv('LOGGER_STDOUT_FORMAT' ) == 'json') {
            $stdout->setFormatter(new JsonFormatter);
        } elseif (getenv('LOGGER_STDOUT_FORMAT' ) == 'cli') {
            $stdout->setFormatter(new CliFormatter);
        } else {
            $stdout->setFormatter(new LineFormatter(
                ($_ENV['LOGGER_STDOUT_FORMAT'] ?? '%level_name%: %message%')."\n"));
        }
    } else {
        $stdout = null;
    }

    if (getenv('LOGGER_TELEGRAM_LEVEL' )) {
        $telegram = new TelegramHandler( Monlogger::toMonologLevel( getenv('LOGGER_TELEGRAM_LEVEL' ) ) );
        $telegram->setBotToken( getenv('LOGGER_TELEGRAM_TOKEN') );
        $telegram->setRecipients( explode(',', getenv('LOGGER_TELEGRAM_RECIPIENTS')) );
    } else {
        $telegram = null;
    }


    if (getenv('LOGGER_SLACK_LEVEL' )) {

        $slack = new SlackWebhookHandler(
            $token              = getenv('LOGGER_SLACK_WEBHOOK'),
            $channel            = getenv('LOGGER_SLACK_CHANNEL'),
            $username           = getenv('LOGGER_SLACK_USERNAME') ?? gethostname(),
            $useAttachment      = true,
            $iconEmoji          = getenv('LOGGER_SLACK_ICON'),
            $useShortAttachment = true,
            $incContextAndExtra = boolval(getenv('LOGGER_SLACK_SIMPLE')),
            $level              = Monlogger::toMonologLevel(getenv('LOGGER_SLACK_LEVEL'))
        );
    } else {
        $slack = null;
    }



    if (getenv('LOGGER_MAIL_LEVEL' )) {

        // Create the Transport
        $transporter = new Swift_SmtpTransport(
            getenv('LOGGER_MAIL_HOST'),  #smtp.gmail.com
            getenv('LOGGER_MAIL_PORT'),  #456
            getenv('LOGGER_MAIL_SSL')    #ssl
        );

        $transporter->setUsername(getenv('LOGGER_MAIL_USER')); # user@gmail.com
        $transporter->setPassword(getenv('LOGGER_MAIL_PASS')); # user password

        // Create the Mailer using your created Transport
        $mailer = new Swift_Mailer($transporter);

        // Create a message
        $message = (new Swift_Message('%level_name% - %message%'));
        $message->setFrom([
            getenv('LOGGER_MAIL_USER') => getenv('LOGGER_MAIL_NAME') ?? gethostname()
        ]);
        $message->setTo( explode(',', getenv('LOGGER_MAIL_RECIPIENTS')) );
        $message->setContentType("text/html");

        $mail = new SwiftMailerHandler(
            $mailer,
            $message,
            Monlogger::toMonologLevel(getenv('LOGGER_MAIL_LEVEL'))
        );
        $mail->setFormatter(new HtmlFormatter());
    } else {
        $mail = null;
    }


    if (getenv('LOGGER_REDIS_LEVEL' )) {
        try {
            $redis = new \Redis;
            $config = parse_url(getenv('LOGGER_REDIS_URI'));
            if (isset($config['query'])) {
                parse_str($config['query'], $qs);
            }

            $connect = !empty($qs['persistent']) ? 'pconnect' : 'connect';
            $redis->$connect(
                $config['host'],
                $config['port'],
                $qs['timeout'] ?? 1
            );
            if (isset($config['path']) && !!$config['path'] && $config['path'] !== '/') {
                $redis->select(ltrim($config['path'], '/'));
            }

            $redis = new RedisHandler($redis, $config['scheme'], Monlogger::toMonologLevel( getenv('LOGGER_REDIS_LEVEL' ) ));
            $redis->setFormatter(new JsonFormatter);

            // $redis->pconnect($config['host'], $config['port'], $config['query']['timeout'] ?? 1, $config['scheme']);
            // $db = intval(ltrim($config['path'], '/'));
            // if ($db > 0) {
            //     $redis->select( $db );
            // }


        } catch (\Exception $exception){
            $redis = null;//new NullHandler;
            $messages[] = [
                Monlogger::ERROR,
                $exception->getMessage(),
                [
                    'exception' => $exception,
                    'file'  => $exception->getFile(),
                    'line'  => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ]
            ];
        }
    } else {
        $redis = null;
    }

    $handlers = !empty($handlers) ? $handlers : array_filter([
        $syslog,
        $stdout,
        $slack,
        $redis,
        $mail,
        $telegram
    ]);

    if (is_null($workerName)) {
        $workerName = gethostname();
    }

    $logger = new Monlogger($workerName);
    $logger->setHandlers($handlers);

    $logger->pushProcessor(function(array $record) : array {
        $record['@env'] = getenv('ENV');
        try {
            $record['@project'] = di()->getProject();
            $record['@domain'] = di()->getDomain();
        } catch(\Throwable $tr) {

        }
        $record['@url'] = php_sapi_name() != "cli" ? ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) : $_SERVER['PHP_SELF'];
        return $record;
    });

    # If there were exceptions during logger creation
    foreach ($messages as $m) {
        call_user_func_array([$logger,'log'], $m);
    }
    return $logger;
};