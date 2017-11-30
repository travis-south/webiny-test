<?php

namespace Apps\Webiny\Php\Entities;

use Apps\Webiny\Php\Lib\Entity\Indexes\IndexContainer;
use Apps\Webiny\Php\Lib\Interfaces\UserInterface;
use Apps\Webiny\Php\Lib\Entity\AbstractEntity;
use Webiny\Component\Crypt\CryptTrait;
use Webiny\Component\Entity\EntityCollection;
use Webiny\Component\Mongo\Index\SingleIndex;
use Webiny\Component\StdLib\StdObject\DateTimeObject\DateTimeObject;

/**
 * Class ApiToken
 *
 * @property string           $id
 * @property string           $token
 * @property string           $owner
 * @property ApiTokenUser     $user
 * @property boolean          $logRequests
 * @property integer          $requests
 * @property DateTimeObject   $lastActivity
 * @property EntityCollection $roles
 * @property EntityCollection $roleGroups
 */
class ApiToken extends AbstractEntity implements UserInterface
{
    use CryptTrait;

    protected static $classId = 'Webiny.Entities.ApiToken';
    protected static $i18nNamespace = 'Webiny.Entities.ApiToken';
    protected static $collection = 'ApiTokens';
    protected static $mask = '{id}';

    public function __construct()
    {
        parent::__construct();
        $this->attr('token')->char()->setSkipOnPopulate()->onToDb(function ($value) {
            if (!$value) {
                $value = $this->crypt()->generateUserReadableString(40);
            }

            return $value;
        })->setToArrayDefault();
        $this->attr('user')->many2one()->setEntity(ApiTokenUser::class);
        $this->attr('owner')->char()->setToArrayDefault();
        $this->attr('description')->char()->setToArrayDefault();
        $this->attr('lastActivity')->datetime()->setToArrayDefault();
        $this->attr('logRequests')->boolean()->setDefaultValue(false)->setToArrayDefault();
        $this->attr('requests')->integer()->setToArrayDefault()->setDefaultValue(0);
        $this->attr('enabled')->boolean()->setDefaultValue(true)->setToArrayDefault();
        $this->attr('roles')->many2many('ApiToken2UserRole', 'ApiToken', 'UserRole')->setEntity(UserRole::class)->onSet(function ($roles) {
            // If not mongo Ids - load roles by slugs
            if (is_array($roles)) {
                foreach ($roles as $i => $role) {
                    if (!$this->wDatabase()->isId($role)) {
                        if (is_string($role)) {
                            $roles[$i] = UserRole::findOne(['slug' => $role]);
                        } elseif (isset($role['id'])) {
                            $roles[$i] = $role['id'];
                        } elseif (isset($role['slug'])) {
                            $roles[$i] = UserRole::findOne(['slug' => $role['slug']]);
                        }
                    }
                }
            }

            return $roles;
        });
        $this->attr('roleGroups')
             ->many2many('ApiToken2UserRoleGroup', 'ApiToken', 'UserRoleGroup')
             ->setEntity(UserRoleGroup::class)
             ->onSet(function ($roleGroups) {
                 // If not mongo Ids - load roles by slugs
                 if (is_array($roleGroups)) {
                     foreach ($roleGroups as $i => $rg) {
                         if (!$this->wDatabase()->isId($rg)) {
                             if (is_string($rg)) {
                                 $roleGroups[$i] = UserRoleGroup::findOne(['slug' => $rg]);
                             } elseif (isset($rg['id'])) {
                                 $roleGroups[$i] = $rg['id'];
                             } elseif (isset($rg['slug'])) {
                                 $roleGroups[$i] = UserRoleGroup::findOne(['slug' => $rg['slug']]);
                             }
                         }
                     }
                 }

                 return $roleGroups;
             });

        // Create a service user for this token
        $this->onAfterCreate(function () {
            $user = new ApiTokenUser();
            $user->meta['apiToken'] = $this->id;
            $user->save();

            $this->user = $user;
            $this->save();
        });
    }

    public function delete($permanent = false)
    {
        $tokenDeleted = parent::delete($permanent);
        if ($tokenDeleted && $this->user) {
            $this->user->delete($permanent);
        }

        return $tokenDeleted;
    }


    protected static function entityIndexes(IndexContainer $indexes)
    {
        parent::entityIndexes($indexes);

        $indexes->add(new SingleIndex('token', 'token'));
        $indexes->add(new SingleIndex('user', 'user'));
    }

    public function getUserRoles()
    {
        $roles = $this->roles->getIterator();
        /* @var $group UserRoleGroup */
        foreach ($this->roleGroups as $group) {
            foreach ($group->roles as $r) {
                $roles[] = $r;
            }
        }

        return $roles;
    }

    public function hasRole($name)
    {
        if (is_string($name)) {
            $name = [$name];
        }

        foreach ($this->getUserRoles() as $role) {
            if (in_array($role->slug, $name)) {
                return true;
            }
        }

        return false;
    }
}