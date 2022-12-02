<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use CodeLathe\Application\Middleware\AdminAuthMiddleware;
use CodeLathe\Application\Middleware\Auth\AuthMiddleware;
use CodeLathe\Application\Middleware\CaptchaMiddleware;
use CodeLathe\Application\Middleware\ImageCacheMiddleware;
use CodeLathe\Application\Middleware\RateLimitMiddleware;
use CodeLathe\Application\Middleware\ResponseFilterMiddleware;
use CodeLathe\Core\Managers\Admin\AdminBiManager;
use CodeLathe\Core\Managers\Admin\AdminReportsManager;
use CodeLathe\Core\Managers\Admin\StatsManager;
use CodeLathe\Core\Managers\Admin\MigrationManager;
use CodeLathe\Core\Managers\Auth\AuthManager;
use CodeLathe\Core\Managers\Action\ActionManager;
use CodeLathe\Core\Managers\Call\CallManager;
use CodeLathe\Core\Managers\Channel\ChannelManager;
use CodeLathe\Core\Managers\ChannelGroup\ChannelGroupManager;
use CodeLathe\Core\Managers\Chat\ChatManager;
use CodeLathe\Core\Managers\ContactForm\ContactFormManager;
use CodeLathe\Core\Managers\EmailManager;
use CodeLathe\Core\Managers\FirebaseManager;
use CodeLathe\Core\Managers\HandshakeManager;
use CodeLathe\Core\Managers\IosPushManager;
use CodeLathe\Core\Managers\Lock\LockManager;
use CodeLathe\Core\Managers\OauthServerManager;
use CodeLathe\Core\Managers\Realtime\RtmManager;
use CodeLathe\Core\Managers\Search\SearchManager;
use CodeLathe\Core\Managers\SystemManager;
use CodeLathe\Core\Managers\Team\TeamManager;
use CodeLathe\Core\Managers\UrlManager;
use CodeLathe\Core\Managers\User\UserManager;
use CodeLathe\Core\Managers\OAuth\OAuthManager;
use CodeLathe\Core\Managers\Admin\AdminManager;
use CodeLathe\Core\Managers\Admin\AuthAdminManager;
use CodeLathe\Core\Managers\Admin\AdminTeamManager;
use CodeLathe\Core\Managers\Password\PasswordManager;
use CodeLathe\Core\Managers\Wopi\WopiManager;
use CodeLathe\Core\Managers\CronManager;
use CodeLathe\Core\Managers\StaticManager;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use CodeLathe\Core\Managers\Files\FileManager;
use CodeLathe\Core\Wiki\WikiManager;

return function (App $app) {

    $api_prefix = '/api/v1';

    ////////////////////////////////////////////////////////////////////

    $app->get('/ping', function(RequestInterface $request, ResponseInterface $response) {
        return JsonOutput::success()->addMeta('pong', 'pong')->write($response);
    });

    // URL shortener routes
    $app->any('/u/{hash:[A-Za-z0-9]{6}}', UrlManager::class.':shorten')->add(RateLimitMiddleware::class);;


    $app->post($api_prefix.'/email.receive', EmailManager::class.':receive')->add(RateLimitMiddleware::class);

    $app->get($api_prefix.'/handshake', HandshakeManager::class.':handshake')->add(RateLimitMiddleware::class);;

    // ... OAuth 2.0 client Related
    $app->group($api_prefix.'/oauth.', function (Group $group) {
        $group->post('google',  OAuthManager::class . ':googleLogin');
        //$group->any('linkedin',  OAuthManager::class . ':linkedInLogin');
        $group->post('apple', OAuthManager::class . ':appleLogin');
        $group->any('logout', OAuthManager::class . ':logout');
    })->add(RateLimitMiddleware::class);

    // ... User Related
    $app->group($api_prefix.'/user.', function (Group $group) {
        $group->post('login', AuthManager::class . ':login');
        $group->post('logout', AuthManager::class . ':logout')->add(AuthMiddleware::class);
        $group->post('login.refresh', AuthManager::class . ':refresh')->add(new AuthMiddleware(true, false, false, false));
        $group->post('create', UserManager::class . ':create')
            ->add(new CaptchaMiddleware())
            ->add(new RateLimitMiddleware(3, 60));
        $group->post('verify', UserManager::class . ':verify');
        $group->post('finalize', UserManager::class . ':finalize')->add(new AuthMiddleware(true, true, false,false));
        $group->get('info', UserManager::class . ':info')->add(new AuthMiddleware(true, true));
        $group->post('image.set', UserManager::class . ':imageSet')->add(AuthMiddleware::class);
        $group->get('image.get', UserManager::class . ':imageGet')->add(new AuthMiddleware(true, true, true, false, true))->add(ImageCacheMiddleware::class);
        $group->post('profile.set', UserManager::class . ':profileSet')->add(AuthMiddleware::class);
        $group->get('alerts', UserManager::class . ':getAlerts')->add(AuthMiddleware::class);
        $group->post('alert.ack', UserManager::class . ':ackAlert')->add(AuthMiddleware::class);
        $group->post('notifications.manage', UserManager::class . ':manageNotifications')->add(new AuthMiddleware(true, true));
        $group->post('notifications.report', UserManager::class . ':reportAbuse')->add(new AuthMiddleware(false, true)); // TODO - Needs a throttle middleware for this kind of request
        $group->post('delete', UserManager::class . ':delete')->add(AuthMiddleware::class);
    });
    $app->post($api_prefix.'/user.verify.refresh', UserManager::class . ':verifyRefresh')->add(new RateLimitMiddleware(1, 30));

    // ... Password Related
    $app->group($api_prefix.'/password.', function (Group $group) {
        $group->post("recover", PasswordManager::class . ':recover');
        $group->post("reset", PasswordManager::class . ':reset');
        $group->post("update", PasswordManager::class . ':update')->add(AuthMiddleware::class);
    })->add(RateLimitMiddleware::class);


    // ... Lock Related
    $app->group($api_prefix.'/lock.', function (Group $group) {
        $group->post('acquire', LockManager::class.':acquire');
        $group->post('release', LockManager::class.':release');
        $group->post('refresh', LockManager::class.':refresh');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);


    // ... Files Related
    $app->group($api_prefix.'/file.', function (Group $group) {
        $group->post('upload', FileManager::class.':upload');
        $group->post('create', FileManager::class.':create');
        $group->post('delete', FileManager::class.':delete');
        $group->post('move', FileManager::class.':move');
        $group->post('copy', FileManager::class.':copy');
        $group->get('versions', FileManager::class.':versions');
        $group->get('synclist', FileManager::class.':synclist');
        $group->get('zip', FileManager::class.':zip');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    $app->group($api_prefix.'/file.', function (Group $group) {
        //$group->get('thumb', FileManager::class.':thumb')->add(ImageCacheMiddleware::class);
        $group->get('download', FileManager::class.':download');
        $group->get('list', FileManager::class.':list');
        $group->get('info', FileManager::class.':info');
    })->addMiddleware(new AuthMiddleware(true, true, true))->add(RateLimitMiddleware::class);

    $app->get($api_prefix.'/file.thumb', FileManager::class.':thumb')
        ->add(ImageCacheMiddleware::class)
        ->add(new AuthMiddleware(true, true, true))
        ->add(new RateLimitMiddleware(10, 60, ['fspath']));

    $app->post($api_prefix . '/file.link.create', FileManager::class . ':createLink')->add(AuthMiddleware::class);
    $app->post($api_prefix . '/file.link.delete', FileManager::class . ':deleteLink')->add(AuthMiddleware::class);

    // .. Internal calls related
    $app->get($api_prefix.'/internal/file.download', FileManager::class . ':internalDownload')
        ->add(AdminAuthMiddleware::class)->add(AuthMiddleware::class);
    $app->post($api_prefix.'/internal/file.sidecarupload', FileManager::class . ':internalSidecarUpload')
        ->add(AdminAuthMiddleware::class)->add(AuthMiddleware::class);

    $app->post($api_prefix.'/internal/cron', CronManager::class . ':dispatch')->addMiddleware(new RateLimitMiddleware(1, 50));


    // ... Channel Related
    $app->group($api_prefix.'/channel.', function (Group $group) {
        $group->get('list', ChannelManager::class . ':list')->addMiddleware(new ResponseFilterMiddleware());
        $group->get('members', ChannelManager::class . ':members');
        $group->post('create', ChannelManager::class . ':create');
        $group->post('invite', ChannelManager::class . ':invite');
        $group->post('oauth.client', ChannelManager::class . ':oauthClient');
        //$group->get('history', ChannelManager::class . ':history');
        $group->post('rename', ChannelManager::class . ':rename');
        $group->post('kick', ChannelManager::class . ':kick');
        $group->post('leave', ChannelManager::class . ':leave');
        $group->post('close', ChannelManager::class . ':close');
        $group->post('remove', ChannelManager::class . ':remove');
        $group->post('activate', ChannelManager::class . ':activate');
        $group->post('image.set', ChannelManager::class . ':imageSet');
        $group->post('user.setrole', ChannelManager::class . ':setUserRole');
        $group->post('update', ChannelManager::class . ':update');
        $group->post('notifications.manage', ChannelManager::class . ':manageNotifications');
        $group->get('member_email', ChannelManager::class . ':getMemberEmail');
        $group->post('join', ChannelManager::class . ':join');
        $group->post('team_join', ChannelManager::class . ':teamJoin');
        $group->post('checkJoin', ChannelManager::class . ':checkJoin');
        $group->post('approveJoin', ChannelManager::class . ':approveJoin');
        $group->post('removeJoin', ChannelManager::class . ':removeJoin');
        $group->get('export', ChannelManager::class . ':export');
        $group->post('one-on-one', ChannelManager::class . ':oneOnOne');
        $group->post('transfer', ChannelManager::class . ':transferOwnership');
        $group->post('favorite', ChannelManager::class . ':favorite');
        $group->post('unfavorite', ChannelManager::class . ':unfavorite');
        $group->get('blocked', ChannelManager::class . ':listBlocked');
        $group->post('block', ChannelManager::class . ':block');
        $group->post('unblock', ChannelManager::class . ':unblock');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    // this route can be accessed through the default auth or using a notification token
    $app->group($api_prefix.'/channel.', function (Group $group) {
        $group->get('history', ChannelManager::class . ':history');
        $group->get('info', ChannelManager::class . ':info');
        $group->post('read-notification', ChannelManager::class . ':readNotification');
        $group->get('image.get', ChannelManager::class . ':imageGet')->add(ImageCacheMiddleware::class);
        $group->get('wiki-tree', ChannelManager::class . ':wikiTree');
        $group->get('links', ChannelManager::class . ':links');
    })->add(new AuthMiddleware(true, true, true, true));

    // ... channel groups related
    $app->group($api_prefix.'/channel.group.', function (Group $group) {
        $group->get('list', ChannelGroupManager::class . ':list');
        $group->post('create', ChannelGroupManager::class . ':create');
        $group->post('update', ChannelGroupManager::class . ':update');
        $group->post('delete', ChannelGroupManager::class . ':delete');
        $group->post('move', ChannelGroupManager::class . ':move');
        $group->post('add', ChannelGroupManager::class . ':add');
        $group->post('remove', ChannelGroupManager::class . ':remove');
    })->add(new AuthMiddleware(true, false, false, true));


    // ... Action Related
    $app->group($api_prefix.'/action.', function (Group $group) {
        $group->post('create', ActionManager::class . ':create');
        $group->get('info', ActionManager::class . ':info');
        $group->post('update', ActionManager::class . ':update');
        $group->post('delete', ActionManager::class . ':delete');
        $group->post('move', ActionManager::class . ':move');
        $group->get('history', ActionManager::class . ':history');
        $group->get('search', ActionManager::class . ':search');


    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);;
    $app->get($api_prefix.'/action.list', ActionManager::class . ':list')->addMiddleware(new AuthMiddleware(true, true, true))->add(RateLimitMiddleware::class);;

    // ... Chat Related
    $app->group($api_prefix.'/chat.', function (Group $group) {
        $group->post('command', ChatManager::class . ':command');
        $group->post('postmessage', ChatManager::class . ':postMessage');
        $group->post('postbotmessage', ChatManager::class . ':postBotMessage');
        $group->post('updatemessage', ChatManager::class . ':updateMessage');
        $group->post('deletemessage', ChatManager::class . ':deleteMessage');
        $group->post('reactmessage', ChatManager::class . ':reactMessage');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    // ... contact form related
    $app->group($api_prefix.'/contact_form.', function (Group $group) {
        $group->get('list', ContactFormManager::class . ':list');
        $group->post('create', ContactFormManager::class . ':create');
        $group->post('update', ContactFormManager::class . ':update');
        $group->post('enable', ContactFormManager::class . ':enable');
        $group->post('disable', ContactFormManager::class . ':disable');
        $group->post('delete', ContactFormManager::class . ':delete');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);;

    $app->post($api_prefix.'/contact_form.fill', ContactFormManager::class . ':fill')->add(RateLimitMiddleware::class);

    // ... System Related
    $app->get($api_prefix.'/system.info', SystemManager::class . ':info')->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);
    $app->post($api_prefix.'/system.ping', SystemManager::class . ':ping')->add(AuthMiddleware::class)->add(new RateLimitMiddleware(60, 60, ['finger_print']));

    // ... Realtime Related
    $app->group($api_prefix.'/rtm.', function (Group $group) {
        $group->get('connect', RtmManager::class . ':connect');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    // ... Firebase Messaging Related
    $app->group($api_prefix . '/firebase.', function (Group $group) {
        $group->post('connect', FirebaseManager::class . ':connect');
        $group->post('disconnect', FirebaseManager::class . ':disconnect');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    // ... iOS push api related
    $app->group($api_prefix . '/ios_push.', function (Group $group) {
        $group->post('connect', IosPushManager::class . ':connect');
        $group->post('disconnect', IosPushManager::class . ':disconnect');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    // ... Wiki Related
    $app->get($api_prefix.'/wiki.get/{path:.*}', WikiManager::class.':get')->add(new AuthMiddleware(true, true, true))->add(RateLimitMiddleware::class);
    $app->post($api_prefix.'/wiki.preview/{path:.*}', WikiManager::class.':preview')->add(new AuthMiddleware());
    $app->get($api_prefix.'/static.get/{path:.*}', StaticManager::class.':get');

    // ... Search Related
    $app->group($api_prefix.'/search.', function (Group $group) {
        $group->get('query', SearchManager::class . ':query');
    })->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    $app->post($api_prefix.'/admin.login', AuthAdminManager::class.':login')->add(RateLimitMiddleware::class);

    // ... Wopi Related
    $app->group($api_prefix.'/wopi.', function (Group $group) {
        //$group->any('access/files/{path_token}', WopiManager::class . ':access');
        $group->any('access/files/{path_token}[/{contents}]', WopiManager::class . ':access');
        $group->get('view', WopiManager::class.':view')->add(AuthMiddleware::class);
        $group->get('edit', WopiManager::class.':edit')->add(AuthMiddleware::class);
    })->add(RateLimitMiddleware::class);

    // ... Oauth Server related
    $app->group($api_prefix.'/oauth.server.', function (Group $group) {
        $group->get('client.list', OauthServerManager::class . ':clientList')->add(AuthMiddleware::class);
        $group->post('client.create', OauthServerManager::class . ':createClient')->add(AuthMiddleware::class);
        $group->get('authorize', OauthServerManager::class . ':authorize')->add(AuthMiddleware::class);
        $group->post('approve', OauthServerManager::class . ':approveAuthorization')->add(AuthMiddleware::class);
        $group->post('access_token', OauthServerManager::class . ':accessToken');
    })->add(RateLimitMiddleware::class);

    // ... team related
    $app->group($api_prefix.'/team.', function (Group $group) {
        $group->post('create', TeamManager::class . ':create')->add(AuthMiddleware::class);
        $group->post('invite', TeamManager::class . ':invite')->add(AuthMiddleware::class);
        $group->post('kick', TeamManager::class . ':kick')->add(AuthMiddleware::class);
        $group->get('list', TeamManager::class . ':list')->add(AuthMiddleware::class);
        $group->get('info', TeamManager::class . ':info')->add(AuthMiddleware::class);
        $group->post('update', TeamManager::class . ':update')->add(AuthMiddleware::class);
        $group->get('members', TeamManager::class . ':members')->add(AuthMiddleware::class);
        $group->post('setting', TeamManager::class . ':setting')->add(AuthMiddleware::class);
        $group->post('user.role', TeamManager::class . ':setRole')->add(AuthMiddleware::class);
        $group->post('channel.owner', TeamManager::class . ':setChannelOwner')->add(AuthMiddleware::class);
        $group->post('channel.open_status', TeamManager::class . ':setChannelOpenStatus')->add(AuthMiddleware::class);
    });

    // ... Call Related
    $app->group($api_prefix.'/call.', function (Group $group) {
        $group->post('create', CallManager::class . ':create')->add(AuthMiddleware::class);
        $group->post('update', CallManager::class . ':update');
        $group->get('join', CallManager::class . ':join');
        $group->post('end', CallManager::class . ':end');
        $group->get('status', CallManager::class . ':status');
        $group->get('invite', CallManager::class . ':invite')->add(AuthMiddleware::class);
        $group->get('invite.accept', CallManager::class . ':inviteAccept')->add(AuthMiddleware::class);

    })->add(RateLimitMiddleware::class);

    // ... System Admin Portal Related
    $app->group($api_prefix.'/admin.', function (Group $group) {
        $group->get('stats.dashboard', AdminManager::class . ':dashboardStats');
        $group->post('user.approve', AdminManager::class . ':userApprove');
        $group->get('user.search', AdminManager::class . ':userSearch');
        $group->post('user.create', AdminManager::class . ':userCreate');
        $group->get('user.info', AdminManager::class . ':userInfo');
        $group->post('user.update', AdminManager::class . ':userUpdate');
        $group->post('user.delete', AdminManager::class . ':userDelete');
        $group->get('user.connections', AdminManager::class . ':userConnections');
        $group->get('user.channels.list', AdminManager::class . ':userChannels');
        $group->get('channel.search', AdminManager::class . ':channelSearch');
        $group->post('channel.create', AdminManager::class . ':channelCreate');
        $group->post('channel.update', AdminManager::class . ':channelUpdate');
        $group->post('channel.delete', AdminManager::class . ':channelDelete');
        $group->get('channel.info', AdminManager::class . ':channelInfo');
        $group->get('channel.user.list', AdminManager::class . ':channelUserList');
        $group->post('channel.user.add', AdminManager::class . ':channelUserAdd');
        $group->post('channel.user.update', AdminManager::class . ':channelUserUpdate');
        $group->post('channel.export', AdminManager::class . ':channelExport');
        $group->get('cache.keys', AdminManager::class . ':cacheKeys');
        $group->get('cache.key.get', AdminManager::class . ':getCacheKey');
        $group->post('cache.key.clear', AdminManager::class . ':clearCacheKey');
        $group->get('stats.websocket', StatsManager::class . ':getWebSocketStats');
        $group->get('stats.redis', StatsManager::class . ':getRedisStats');
        $group->get('stats.connections', StatsManager::class . ':getConnectionsStats');
        $group->get('stats.public-channels', StatsManager::class . ':getPublicChannelStats');
        $group->get('stats.kafka/{command}', StatsManager::class . ':kafkaStats');
        $group->get('stats.zoo', StatsManager::class . ':zooTree');
        $group->get('stats.elastic.indices', StatsManager::class . ':getElasticIndices');
        $group->get('stats.elastic.query', StatsManager::class . ':queryElasticIndex');

        $group->any('dbversion.upgrade', MigrationManager::class . ':upgrade');
        $group->any('dbversion.info', MigrationManager::class . ':info');
        $group->any('dbversion.list', MigrationManager::class . ':list');

        $group->get('team.search', AdminTeamManager::class . ':search');
        $group->post('team.create', AdminTeamManager::class . ':create');
        $group->post('team.update', AdminTeamManager::class . ':update');
        $group->post('team.delete', AdminTeamManager::class . ':delete');
        $group->get('team.info', AdminTeamManager::class . ':info');
        $group->get('team.user.list', AdminTeamManager::class . ':listUsers');
        $group->post('team.user.add', AdminTeamManager::class . ':addUser');
        $group->post('team.user.delete', AdminTeamManager::class . ':deleteUser');
        $group->get('notification.abuse-report', AdminManager::class . ':notificationAbuseReport');
        $group->post('notification.abuse-report.delete/{id:[0-9]+}', AdminManager::class . ':notificationAbuseReportDelete');
        $group->post('db.commands/{command}/run', AdminManager::class . ':runDbCommand');
        $group->get('db.commands', AdminManager::class . ':getDbCommands');
        $group->get('bi.cubes', AdminBiManager::class . ':cubes');
        $group->get('bi.cube.{cube}', AdminBiManager::class . ':cubeData');
        $group->get('reports.list', AdminReportsManager::class . ':list');
        $group->get('reports.execute', AdminReportsManager::class . ':execute');

    })->add(AdminAuthMiddleware::class)->add(AuthMiddleware::class)->add(RateLimitMiddleware::class);

    // catch-all routes to handle cors pre-flight requests
    //  - This makes cors to work, but have some problems:
    //    - If I send an `OPTIONS invalid.route` I'll get a 200 as response (not correct)
    //    - If I send a `GET invalid.route` I'll get a 404 response (correct)
    //    - If I send a `GET user.login` (it only accepts POST), I'll get a 404 (more or less correct, the perfect
    //      situation should be 405, since the route exists, but we're trying to use it with a wrong method.
    //
    // IMPORTANT: Slim App class EXTENDS the router, so it's not possible to override the router and inject it into
    // the application to always include the OPTIONS with GET and POST (not possible on an elegant way)
    // We can replace all the `get()` and `post()` methods on this file with a map to `get,options` or `post,options`.
    // It works, but will make our routes file complex and error prone, so we're going with this approach for now.
    $app->options($api_prefix.'/{route:.+}', function($request, $response) {
        return $response;
    });
    $app->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $api_prefix.'/{route:.+}', function ($request, $response) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    });


};
