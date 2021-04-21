<?php

/*
 * This file is part of fof/doorman.
 *
 * Copyright (c) Reflar.
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace FoF\Doorman\Api\Controllers;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Doorman\Api\Serializers\DoorkeySerializer;
use FoF\Doorman\Doorkey;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tobscure\JsonApi\Document;

class SendInvitesController extends AbstractCreateController
{
    /**
     * @var Dispatcher
     */
    protected $bus;

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var UrlGenerator
     */
    protected $url;

    public $serializer = DoorkeySerializer::class;

    /**
     * @param Dispatcher $bus
     */
    public function __construct(Dispatcher $bus, Mailer $mailer, TranslatorInterface $translator, UrlGenerator $url)
    {
        $this->bus = $bus;
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->url = $url;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Document               $document
     *
     * @throws \Flarum\User\Exception\PermissionDeniedException
     *
     * @return mixed
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $request->getAttribute('actor')->assertAdmin();

        $data = $request->getParsedBody();

        $doorkey = Doorkey::findOrFail($data['doorkeyId']);

        $title = resolve(SettingsRepositoryInterface::class)->get('forum_title');

        $subject = resolve(SettingsRepositoryInterface::class)->get('forum_title').' - '.$this->translator->trans('fof-doorman.forum.email.subject');

        $body = $this->translator->trans('fof-doorman.forum.email.body', [
            '{forum}' => $title,
            '{url}'   => $this->url->to('forum')->base(),
            '{code}'  => $doorkey->key,
        ]);

        foreach ($data['emails'] as $email) {
            $this->mailer->raw(
                $body,
                function (Message $message) use ($subject, $email) {
                    $message->to($email)->subject($subject);
                }
            );
        }

        return $doorkey;
    }
}
