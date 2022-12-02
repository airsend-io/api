Authorization Subsystem
====================

This system basically authorizes an already authenticated user to do 
something on the system.

### Authorizing actions (checking permissions)

To use this subsystem, just instantiate the `AuthorizationHandler`, and
call the authorize method, passing the user, the resource that you want
to act on, and the action that you want to do.

Exemple (ask permission to write to a channel):
```php
    $authorizationHandler->authorize($user, $channel, 'write');

 ```

The User object implements two shortcuts for this call:
```php
    $user->can('write', $channel);
    $user->cannot('write', $channel);
```

### Implementing new actions

To implement new permissions, you have to create a Gate class for the resource 
that you want to provide permissions. Here are the steps:
1. Create the Gate class, extending the `AbstractGate` class (something like `ChannelGate`);
2. Write one method for each action, following the pattern:
  - The method name MUST be the action;
  - The method first parameter MUST be an User instance;
  - The method second parameter is optional, but in most cases it will be the resource instance (like a Channel);
  - The method return MUST be bool
  - The method code should do whatever is necessary to check user permissions and return yes or no. Any
additional service that is necessary to accomplish this task SHOULD be injected through the constructor.
  - Method signature example: 
```php
    public function write(User $user, Channel $channel): bool;
```
3. Register the new class on `AuthorizationHandler::registerGates()`, like this:
```
Channel::class => ChannelGate::class

```

### Authorizing resource creation (or any "resourceless" action)

When the action is a creation, there is no resource to pass (when you're creating a channel, 
you can't ask authorization for a particular channel, it doesn't make sense).

At this case, you can just pass the `Channel` class as the resource:
```php
    $authorizationHandler->authorize($user, Channel::class, 'create');

 ```

Same works for the User's shortcuts:
```php
    $user->can('create', Channel::class);
    $user->cannot('create', Channel::class);
```

For the Gate method implementation, just receive the user as parameter:
```php
    public function create(User $user): bool;
```

### Before hook

Sometimes you want to bypass any authorization logic, based on the user
role or something like that (say a super admin user must have access to everything
or an Guest user shouldn't have any access at all). At this case,
you can use the before method from the `AbstractGate`.

For convenience, we already have a `AuthorizeSystemAdminTrait`, that allow
anything to the system admin user. You just have to use the trait on the
gate where you want to activate this behavior.