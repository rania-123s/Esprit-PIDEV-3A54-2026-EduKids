<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationParticipantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ConversationVoter extends Voter
{
    public const VIEW = 'CONVERSATION_VIEW';
    public const MESSAGE = 'CONVERSATION_MESSAGE';
    public const MANAGE_MEMBERS = 'CONVERSATION_MANAGE_MEMBERS';
    public const LEAVE = 'CONVERSATION_LEAVE';

    public function __construct(private readonly ConversationParticipantRepository $participantRepository)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MESSAGE, self::MANAGE_MEMBERS, self::LEAVE], true)
            && $subject instanceof Conversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Conversation) {
            return false;
        }

        $roles = $user->getRoles();
        $canUseChat = in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARENT', $roles, true);
        if (!$canUseChat) {
            return false;
        }

        $membership = $this->participantRepository->findActiveForConversationAndUser($subject, $user);
        if ($membership === null) {
            return false;
        }

        return match ($attribute) {
            self::VIEW, self::MESSAGE => true,
            self::LEAVE => $subject->isGroup(),
            self::MANAGE_MEMBERS => $subject->isGroup() && $membership->isAdmin(),
            default => false,
        };
    }
}
