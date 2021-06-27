<?php

namespace RTippin\Messenger\Support;

use Exception;
use Illuminate\Support\Collection;
use RTippin\Messenger\Actions\Messages\StoreSystemMessage;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Models\Call;
use RTippin\Messenger\Models\GhostUser;
use RTippin\Messenger\Models\Message;
use RTippin\Messenger\Models\Participant;
use RTippin\Messenger\Models\Thread;

class MessageTransformer
{
    /**
     * @param Message $message
     * @return string
     */
    public static function transform(Message $message): string
    {
        try {
            if ($message->notSystemMessage()) {
                return self::sanitizedBody($message->body);
            }

            $bodyJson = self::decodeBodyJson($message->body);

            switch ($message->type) {
                case 90: return self::transformVideoCall($message, $bodyJson);
                case 95: return self::transformAdminRemoved($message, $bodyJson);
                case 96: return self::transformAdminAdded($message, $bodyJson);
                case 98: return self::transformParticipantRemoved($message, $bodyJson);
                case 99: return self::transformParticipantsAdded($message, $bodyJson);
                default: return self::sanitizedBody($message->body);
            }
        } catch (Exception $e) {
            report($e);
        }

        return 'Message Error';
    }

    /**
     * @param string $body
     * @return string
     */
    public static function sanitizedBody(string $body): string
    {
        return htmlspecialchars($body);
    }

    /**
     * @param string $body
     * @return array|null
     */
    public static function decodeBodyJson(string $body): ?array
    {
        return json_decode($body, true);
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @return array
     */
    public static function makeJoinedWithInvite(Thread $thread, MessengerProvider $provider): array
    {
        return self::generateStoreResponse($thread, $provider, 'joined', 'PARTICIPANT_JOINED_WITH_INVITE');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param Call $call
     * @return array
     */
    public static function makeVideoCall(Thread $thread,
                                         MessengerProvider $provider,
                                         Call $call): array
    {
        $body = (new Collection(['call_id' => $call->id]))->toJson();

        return self::generateStoreResponse($thread, $provider, $body, 'VIDEO_CALL');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @return array
     */
    public static function makeGroupAvatarChanged(Thread $thread, MessengerProvider $provider): array
    {
        return self::generateStoreResponse($thread, $provider, 'updated the avatar', 'GROUP_AVATAR_CHANGED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @return array
     */
    public static function makeThreadArchived(Thread $thread, MessengerProvider $provider): array
    {
        $body = $thread->isGroup() ? 'archived the group' : 'archived the conversation';

        return self::generateStoreResponse($thread, $provider, $body, 'THREAD_ARCHIVED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param string $subject
     * @return array
     */
    public static function makeGroupCreated(Thread $thread,
                                            MessengerProvider $provider,
                                            string $subject): array
    {
        return self::generateStoreResponse($thread, $provider, "created $subject", 'GROUP_CREATED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param string $subject
     * @return array
     */
    public static function makeGroupRenamed(Thread $thread,
                                            MessengerProvider $provider,
                                            string $subject): array
    {
        return self::generateStoreResponse($thread, $provider, "renamed the group to $subject", 'GROUP_RENAMED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param Participant $participant
     * @return array
     */
    public static function makeParticipantDemoted(Thread $thread,
                                                  MessengerProvider $provider,
                                                  Participant $participant): array
    {
        $body = self::generateParticipantJson($participant);

        return self::generateStoreResponse($thread, $provider, $body, 'DEMOTED_ADMIN');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param Participant $participant
     * @return array
     */
    public static function makeParticipantPromoted(Thread $thread,
                                                   MessengerProvider $provider,
                                                   Participant $participant): array
    {
        $body = self::generateParticipantJson($participant);

        return self::generateStoreResponse($thread, $provider, $body, 'PROMOTED_ADMIN');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @return array
     */
    public static function makeGroupLeft(Thread $thread, MessengerProvider $provider): array
    {
        return self::generateStoreResponse($thread, $provider, 'left', 'PARTICIPANT_LEFT_GROUP');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param Participant $participant
     * @return array
     */
    public static function makeRemovedFromGroup(Thread $thread,
                                                MessengerProvider $provider,
                                                Participant $participant): array
    {
        $body = self::generateParticipantJson($participant);

        return self::generateStoreResponse($thread, $provider, $body, 'PARTICIPANT_REMOVED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param Collection $participants
     * @return array
     */
    public static function makeParticipantsAdded(Thread $thread,
                                                 MessengerProvider $provider,
                                                 Collection $participants): array
    {
        $body = $participants->transform(fn (Participant $participant) => [
            'owner_id' => $participant->owner_id,
            'owner_type' => $participant->owner_type,
        ])->toJson();

        return self::generateStoreResponse($thread, $provider, $body, 'PARTICIPANTS_ADDED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param string $botName
     * @return array
     */
    public static function makeBotAdded(Thread $thread,
                                        MessengerProvider $provider,
                                        string $botName): array
    {
        return self::generateStoreResponse($thread, $provider, "added $botName BOT", 'BOT_ADDED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param string $oldName
     * @param string $botName
     * @return array
     */
    public static function makeBotRenamed(Thread $thread,
                                          MessengerProvider $provider,
                                          string $oldName,
                                          string $botName): array
    {
        return self::generateStoreResponse($thread, $provider, "renamed the BOT ( $oldName ) to $botName", 'BOT_RENAMED');
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param string $botName
     * @return array
     */
    public static function makeBotRemoved(Thread $thread,
                                          MessengerProvider $provider,
                                          string $botName): array
    {
        return self::generateStoreResponse($thread, $provider, "removed $botName BOT", 'BOT_REMOVED');
    }

    /**
     * @param Message $message
     * @param array $data
     * @return GhostUser|MessengerProvider
     */
    public static function locateContentOwner(Message $message, array $data)
    {
        /** @var Participant $participant */
        $participant = $message->thread->participants
            ->where('owner_id', '=', $data['owner_id'])
            ->where('owner_type', '=', $data['owner_type'])
            ->first();

        if ($participant && Messenger::isValidMessengerProvider($participant->owner)) {
            return $participant->owner;
        }

        /** @var MessengerProvider|null $owner */
        $owner = null;

        if (Messenger::isValidMessengerProvider($data['owner_type'])) {
            $owner = Messenger::findAliasProvider($data['owner_type'])::find($data['owner_id']);
        }

        return $owner ?: Messenger::getGhostProvider();
    }

    /**
     * @param Message $message
     * @param array $bodyJson
     * @return string
     */
    private static function transformVideoCall(Message $message, array $bodyJson): string
    {
        /** @var Call $call */
        $call = $message->thread->calls()
            ->videoCall()
            ->withCount('participants')
            ->find($bodyJson['call_id']);

        if ($call && $call->participants_count > 1) {
            $names = '';
            $participants = $call->participants()
                ->notProvider($message->owner)
                ->with('owner')
                ->limit(3)
                ->get();

            if ($call->participants_count > 4) {
                $remaining = $call->participants_count - 4;
                foreach ($participants as $participant) {
                    if ($participants->last()->id === $participant->id) {
                        $names .= " {$participant->owner->getProviderName()}, and $remaining others";
                    } else {
                        $names .= " {$participant->owner->getProviderName()},";
                    }
                }
            } else {
                foreach ($participants as $participant) {
                    if ($participants->count() === 1
                        || ($participants->count() === 2
                            && $participants->first()->id === $participant->id)) {
                        $names .= " {$participant->owner->getProviderName()}";
                    } elseif ($participants->last()->id === $participant->id) {
                        $names .= " and {$participant->owner->getProviderName()}";
                    } else {
                        $names .= " {$participant->owner->getProviderName()},";
                    }
                }
            }

            return 'was in a video call with '.trim($names);
        }

        return 'was in a video call';
    }

    /**
     * @param Message $message
     * @param array $bodyJson
     * @return string
     */
    private static function transformAdminAdded(Message $message, array $bodyJson): string
    {
        return 'promoted '.self::locateContentOwner($message, $bodyJson)->getProviderName();
    }

    /**
     * @param Message $message
     * @param array $bodyJson
     * @return string
     */
    private static function transformAdminRemoved(Message $message, array $bodyJson): string
    {
        return 'demoted '.self::locateContentOwner($message, $bodyJson)->getProviderName();
    }

    /**
     * @param Message $message
     * @param array $bodyJson
     * @return string
     */
    private static function transformParticipantRemoved(Message $message, array $bodyJson): string
    {
        return 'removed '.self::locateContentOwner($message, $bodyJson)->getProviderName();
    }

    /**
     * @param Message $message
     * @param array $bodyJson
     * @return string
     */
    private static function transformParticipantsAdded(Message $message, array $bodyJson): string
    {
        $names = '';

        if (count($bodyJson) > 3) {
            $remaining = count($bodyJson) - 3;
            $sliced = array_slice($bodyJson, 0, 3);
            foreach ($sliced as $key => $owner) {
                if ($key === array_key_last($sliced)) {
                    $names .= ' '.self::locateContentOwner($message, $owner)->getProviderName().", and $remaining others";
                } else {
                    $names .= ' '.self::locateContentOwner($message, $owner)->getProviderName().',';
                }
            }
        } else {
            foreach ($bodyJson as $key => $owner) {
                if (count($bodyJson) === 1 || (count($bodyJson) === 2 && $key === array_key_first($bodyJson))) {
                    $names .= ' '.self::locateContentOwner($message, $owner)->getProviderName();
                } elseif ($key === array_key_last($bodyJson)) {
                    $names .= ' and '.self::locateContentOwner($message, $owner)->getProviderName();
                } else {
                    $names .= ' '.self::locateContentOwner($message, $owner)->getProviderName().',';
                }
            }
        }

        return 'added '.trim($names);
    }

    /**
     * @param Participant $participant
     * @return string
     */
    private static function generateParticipantJson(Participant $participant): string
    {
        return (new Collection([
            'owner_id' => $participant->owner_id,
            'owner_type' => $participant->owner_type,
        ]))->toJson();
    }

    /**
     * @param Thread $thread
     * @param MessengerProvider $provider
     * @param string $body
     * @param string $type
     * @return array
     * @see StoreSystemMessage
     */
    private static function generateStoreResponse(Thread $thread,
                                                  MessengerProvider $provider,
                                                  string $body,
                                                  string $type): array
    {
        return [$thread, $provider, $body, $type];
    }
}
