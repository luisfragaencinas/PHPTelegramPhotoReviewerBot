<?php

require_once 'vendor/autoload.php';

try {
    $configuration = parse_ini_file('configuration.ini');
    $password = $configuration['password'];
    $reviewers_file = $configuration['reviewers_file'];
    $url_accept = $configuration['url_accept'];
    $token_accept = $configuration['token_accept'];
    $telegram_img = $configuration['telegram_img'];
    $photos_route = $configuration['photos_route'];
    $admin_prefix = 'admin';
    $bot_accept = new \Telegram\Bot\Api($token_accept);

    $welcome_msg = 'Bot de revisión y almacenamiento de fotos.
*USUARIO NORMAL:*

- Para añadir una *foto* sólo tienes que *reenviarla a este chat*.

- Los *revisores* recibiran la foto, si la *aceptan* se descargará en el servidor.

- Sólo puedes tener *una foto en cada sesión*, cada vez que mandas una nueva foto se *sobreescribe* la anterior.

- En cuanto un revisor acepta tu foto deberías recibir un mensaje con la confirmación.

- Si no has recibido ningún mensaje puede ser porque la foto aun no fue revisada, no fue aceptada o era la misma foto que la anterior.

- Escribe o haz click en el comando /foto para saber si ya has subido una foto en esta sesión.

*REVISOR:*
- Introduce la *contraseña* en cualquier momento para autenticarte y comenzar a recibir fotos para revisar.
     
- Si alguna vez te *equivocas rechazando* un foto que querías aceptar, *reenvía* la foto a este mismo chat y se volverá a procesar.';

    $photo_msg = 'Ou Yeah!!'
        .PHP_EOL.'/si_%PHOTO_ID%_%ACCEPT_ID%_%USER_ID%_%CHAT_ID%'
        .PHP_EOL.PHP_EOL.'Ni de coña.'
        .PHP_EOL.'/no_%PHOTO_ID%_%ACCEPT_ID%_%USER_ID%_%CHAT_ID%'.PHP_EOL;

    $notify = $_GET['notify'];

    if (isset($notify)) {
        $delete = array();
        $reviewers = array_unique(unserialize(file_get_contents($reviewers_file)));
        foreach ($reviewers as $chat_id) {
            try {
                $accept_sent = $bot_accept->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Este chat (' . $chat_id . ') está revisando fotos'
                ]);
            } catch (Exception $e) {
                //Remove from reviewers
                array_push($delete, $chat_id);
            }
            file_put_contents($reviewers_file, serialize(array_diff($reviewers, $delete)));
        }
    }
    else {
        $update = $bot_accept->getWebhookUpdate();
        $chat_id = $update->getMessage()->chat->id;
        $text = $update->getMessage()->text;
        $reviewers = array_unique(unserialize(file_get_contents($reviewers_file)));
        $is_bot = $update->getMessage()->from->is_bot;
        $user_id = $update->getMessage()->from->id;
        $is_admin = (($key = array_search($chat_id, $reviewers)) !== false);

        $resent_photos = $update->getMessage()->photo;

        $command_id = $update->getMessage()->message_id;
        //Retry photo
        if (!$is_bot && isset($resent_photos)) {
            try {
                //get biggest photo
                $maxsize = -1;
                $maxindex = -1;
                for ($i = 0; $i <= sizeof($resent_photos); $i++) {
                    if ($resent_photos[$i]['file_size'] > $maxsize) $maxindex = $i;
                }
                $file_id = $resent_photos[$maxindex]['file_id'];
                //Admin can upload any photo
                if($is_admin)
                {
                    $bot_accept->deleteMessage([
                        'chat_id' => $chat_id,
                        'message_id' => $command_id,
                    ]);
                    $photo_sent = $bot_accept->sendPhoto([
                        'chat_id' => $chat_id,
                        'photo' => $file_id,
                        'caption' => '¿Aceptar esta foto?'
                    ]);
                    $accept_sent = $bot_accept->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => str_replace('%CHAT_ID%', $chat_id, str_replace('%USER_ID%', $admin_prefix, str_replace('%PHOTO_ID%', $photo_sent->message_id, $photo_msg)))
                    ]);
                    $bot_accept->editMessageText([
                            'chat_id' => $chat_id,
                            'message_id' => $accept_sent->message_id,
                            'text' => str_replace("%ACCEPT_ID%", $accept_sent->message_id, $accept_sent->text)
                        ]
                    );
                }
                else //user photos are forwarded to admins
                {
                    if(0 < sizeof($reviewers))
                    {
                        if(file_exists($photos_route . $user_id . '.jpg'))
                        {
                            $bot_accept->sendMessage([
                                'chat_id' => $chat_id,
                                'text' => 'Ya habías subido una foto, si es aceptada tu foto se sobreescribirá'
                            ]);
                        }
                        $bot_accept->sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Enviando la foto a los revisores'
                        ]);
                        foreach ($reviewers as $reviewer_chat) {
                            try {
                                $photo_sent = $bot_accept->sendPhoto([
                                    'chat_id' => $reviewer_chat,
                                    'photo' => $file_id,
                                    'caption' => '¿Aceptar esta foto?'
                                ]);
                                $accept_sent = $bot_accept->sendMessage([
                                    'chat_id' => $reviewer_chat,
                                    'text' => str_replace('%CHAT_ID%', $chat_id, str_replace('%USER_ID%', $user_id, str_replace('%PHOTO_ID%', $photo_sent->message_id, $photo_msg)))
                                ]);
                                $bot_accept->editMessageText([
                                        'chat_id' => $reviewer_chat,
                                        'message_id' => $accept_sent->message_id,
                                        'text' => str_replace("%ACCEPT_ID%", $accept_sent->message_id, $accept_sent->text)
                                    ]
                                );
                            } catch (Exception $e) {
                                //Remove from reviewers
                            }
                        }
                    }
                    else
                    {
                        $bot_accept->sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Lo siento, ahora mismo no hay nadie aceptando fotos.'
                        ]);
                    }
                }
            } catch (Exception $e)
            {
                $response = $bot_accept->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $e->getMessage()
                ]);
            }
        }
        //COMMANDS
        else if (isset($text)) {
            if (0 == strncmp('/start', $text, 6)) {
                $response = $bot_accept->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $welcome_msg,
                    'parse_mode' => 'Markdown'
                ]);
            }
            //DO I HAVE PHOTO
            else if (0 == strncmp('/foto', $text, 5)) {
                if ($is_admin) {
                    $response = $bot_accept->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Comando reservador para usuarios normales, los revisores pueden subir un número ilimitado de fotos.'
                    ]);
                }
                else if (file_exists($photos_route . $user_id . '.jpg')) {
                    $response = $bot_accept->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Sí has subido una foto para esta sesión'
                    ]);
                } else {
                    $response = $bot_accept->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Aún no cuentas con ninguna foto en esta sesión'
                    ]);
                }
            }
            //LOGIN AS ADMIN
            else if (0 == strcmp($password, $text)) {
                $reviewers = unserialize(file_get_contents($reviewers_file));
                if (($key = array_search($chat_id, $reviewers)) !== false) {
                    $response = $bot_accept->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Este chat (' . $chat_id . ') ya estaba registrado para la revisión de fotos'
                    ]);
                } else {
                    array_push($reviewers, $chat_id);
                    file_put_contents($reviewers_file, serialize(array_unique($reviewers)));
                    $response = $bot_accept->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'El chat (' . $chat_id . ') ha sido registrado para la revisión de fotos'
                    ]);
                }
            //ACCEPT
            } else if ($is_admin && (0 == strncmp('/si', $text, 3))) {
                $parameters = explode("_", $text);
                //Delete the options given
                $bot_accept->deleteMessage([
                    'chat_id' => $chat_id,
                    'message_id' => $parameters[2],
                ]);
                //Delete the option taken (commmand message)
                $bot_accept->deleteMessage([
                    'chat_id' => $chat_id,
                    'message_id' => $command_id,
                ]);
                //Edit the photo caption
                $editted = $bot_accept->editMessageCaption([
                        'chat_id' => $chat_id,
                        'message_id' => $parameters[1],
                        'caption' => 'FOTO ACEPTADA!!'
                    ]
                );
                //Download the biggest photo
                try {
                    $photos = $editted->photo;
                    $maxsize = -1;
                    $maxindex = 0;
                    for ($i = 0; $i <= sizeof($photos); $i++) {
                        if($photos[$i]['file_size'] > $maxsize) $maxindex = $i;
                    }
                    $file_id = $photos[$maxindex]['file_id'];
                    $file = $bot_accept->getFile([
                        'file_id' => $file_id
                    ]);
                    $photo_url = $telegram_img . $token_accept . "/" . ($file->file_path);
                    $photo_data = file_get_contents($photo_url);
                    if (0 == strcmp($parameters[3], $admin_prefix)) {
                        if (!is_dir($photos_route)) {
                            mkdir($photos_route);

                        }
                        file_put_contents($photos_route . $admin_prefix . '_' . md5($photo_data) . '.jpg', $photo_data);
                    } else {
                        if (is_numeric($parameters[3])) {
                            /*if (!is_dir($photos_route)) {
                                mkdir($photos_route);
                            }*/
                            $old_md5 = -1;
                            if (file_exists($photos_route . $parameters[3] . '.jpg')) {
                                $old_md5 = md5(file_get_contents($photos_route . $parameters[3] . '.jpg'));
                            }
                            if($old_md5 != md5($photo_data))
                            {
                                /*if (!is_dir($photos_route)) {
                                    mkdir($photos_route);
                                }*/
                                file_put_contents($photos_route . $parameters[3] . '.jpg', $photo_data);
                                if(is_numeric($parameters[4]))
                                {
                                    $bot_accept->sendMessage([
                                        'chat_id' => $parameters[4],
                                        'text' => 'Enhorabuena, tu foto se ha guardado'
                                    ]);
                                }
                            }
                            else
                            {
                                $bot_accept->editMessageCaption([
                                        'chat_id' => $chat_id,
                                        'message_id' => $parameters[1],
                                        'caption' => 'ESTA FOTO YA HABÍA SIDO ACEPTADA'
                                    ]
                                );
                            }
                        }
                    }
                } catch (Exception $e) {
                    $response = $bot_accept->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => $e->getMessage()
                    ]);
                }
            //DENY
            } else if ($is_admin && (0 == strncmp('/no', $text, 3))) {
                $parameters = explode("_", $text);
                //Delete the options given
                $bot_accept->deleteMessage([
                    'chat_id' => $chat_id,
                    'message_id' => $parameters[2],
                ]);
                //Delete the option taken (commmand message)
                $bot_accept->deleteMessage([
                    'chat_id' => $chat_id,
                    'message_id' => $command_id,
                ]);
                //Edit the photo caption
                $editted = $bot_accept->editMessageCaption([
                        'chat_id' => $chat_id,
                        'message_id' => $parameters[1],
                        'caption' => 'FOTO RECHAZADA!!'
                    ]
                );
            }
        }
    }
} catch (Exception $e) {
    d($e);
}