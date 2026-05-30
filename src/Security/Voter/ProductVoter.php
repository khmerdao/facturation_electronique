<?php
declare(strict_types=1);
namespace App\Security\Voter;
use App\Entity\Product;
use App\Entity\User;
use App\Entity\Enum\Role;
use App\Security\TenantContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ProductVoter extends Voter
{
    public const VIEW   = 'PRODUCT_VIEW';
    public const CREATE = 'PRODUCT_CREATE';
    public const EDIT   = 'PRODUCT_EDIT';
    public const DELETE = 'PRODUCT_DELETE';

    public function __construct(private readonly TenantContext $tenantContext) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE], true)) return false;
        if ($attribute === self::CREATE) return true;
        return $subject instanceof Product;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;
        if ($user->isSuperAdmin()) return true;
        $membership = $this->tenantContext->getMembership();
        if (!$membership) return false;
        $role = $membership->getRole();
        return match ($attribute) {
            self::VIEW             => true,
            self::CREATE, self::EDIT => $role->level() >= Role::ACCOUNTANT->level(),
            self::DELETE           => $role->level() >= Role::ADMIN->level(),
            default                => false,
        };
    }
}
