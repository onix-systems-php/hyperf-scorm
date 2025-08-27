# onix-systems-php/hyperf-support

**Hyperf-support** is a package for fluently managing your tickets and comments within Slack, Trello, Jira and other systems. Made by [onix-systems-php](https://github.com/onix-systems-php)

## Installation:
```shell
composer require onix-systems-php/hyperf-support
```

## Publishing the config:
```shell
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-support
```

## Configuration

### Configure `app`
1. `domain` - application URL e.g. (https://github.com).
2. `name` - application name.
3. `team_name` - name of your support team.
4. `user_model_class` - User model path. Then implement `OnixSystemsPHP\HyperfSupport\Contract\SupportUserInterface` contract in the model class.

### Configure `integrations.trello`
1. `key` - API Key. (You may find it here: https://trello.com/power-ups/admin)
2. `token` - Authorization token.
3. `webhook_url` - `app.domain` + `/v1/support/webhooks/trello`.
4. `board_name` - Trello board name.
5. `members` - For each type of ticket specify members which should be attached to the card on Trello.
6. `lists` - Specify mapping for each status of ticket with corresponding list on Trello.
7. `custom_fields` - Determine which custom fields should be on card on Trello.
8. `trigger_lists` - Specify trigger lists on Trello. These lists determine whether to notify users if the ticket moved in one of these lists.
9. `is_private_discussion` - This option must be `true` or `false`. If `true`, discussion under the ticket on Trello will be private and anyone can see it except on Trello.
10. `keys_to_source` - Specify `your_api_username` => `your_source`.

### Configure `integrations.slack`
1. `token` - Bot Authorization key.
2. `channel_id` - Slack channel id.
3. Don't forget to enable subscriptions for your Slack bot and specify request URL: `app.domain` + `/v1/support/webhooks/slack`.
4. `app_icon` - Your application's icon URL. e.g.
5. `trello_icon` - Trello icon URL. (optional)
6. `members` - For each type of ticket specify members which should be mentioned on Slack ticket. **Without '@'.**
7. `custom_fields` - Determine which custom fields should be showed on Slack ticket.
8. `is_private_discussion` - This option must be `true` or `false`. If `true`, discussion under the ticket on Slack will be private and anyone can see it except on Slack.
9. `keys_to_source` - Specify `your_slack_channel_id` => `your_source`.

### Configure `integrations.jira`
1. `base_url` - Jira instance URL (e.g., https://your-domain.atlassian.net).
2. `username` - Jira username or email.
3. `password` - Jira API token (not your login password).
4. `project_key` - Jira project key (e.g., "PROJ").
5. `custom_fields` - Map custom field IDs to field names.
6. `webhook_url` - `app.domain` + `/v1/support/webhooks/jira`.
7. `issue_types` - Map ticket types to Jira issue types.
8. `priorities` - Map priority levels to Jira priorities.
9. `keys_to_source` - Specify `your_jira_project_key` => `your_source`.

### Configure `routes`
`require_once './vendor/onix-systems-php/hyperf-support/publish/routes.php';`

## Basic Usage

### Creating simple ticket:
Try to send this `JSON` to `/v1/support/tickets` via `POST` method.
```json
{
  "source": "default",
  "title": "Lorem ipsum.",
  "content": "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.",
  "custom_fields": {
    "type": "Tweak",
    "level": 3,
    "priority": "Medium",
    "status": "New"
  },
  "page_url": "https://google.com"
}
```

You should get something like this object:
```json
{
    "id": 1,
    "title": "Lorem ipsum.",
    "content": "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.",
    "source": "default",
    "custom_fields": {
        "type": "Tweak",
        "level": 3,
        "status": "New",
        "priority": "Medium"
    },
    "created_by": 6,
    "modified_by": null,
    "deleted_by": null,
    "completed_at": null,
    "trello_id": "660bc45ce19c204556caf1f5",
    "trello_short_link": "qTHPdFoL",
    "slack_id": "1712047194.779679",
    "jira_id": "PROJ-123",
    "jira_short_link": "PROJ-123",
    "page_url": null,
    "created_at": "2024-04-02 08:39:54",
    "updated_at": "2024-04-02 08:40:36",
    "deleted_at": null,
    "files": []
}
```

Finally, it should appear on Slack, Trello, and Jira.

### Creating ticket with files:
Logic the same as for creating simple ticket, but you need to pass array with files' IDs:
```json
{
    ...
    "files": [1, 2, 3]
}
```

Finally, the ticket should appear on Slack, Trello, and Jira with attached files.

### Updating ticket on Trello.
Once the `ticket.done_status` is "Done" everytime when ticket moved to Done list on Trello the ticket will be marked as "completed".

### Updating ticket on Jira.
When a ticket is updated in your system, it will automatically sync to Jira with the latest information including title, description, and custom fields.

## Architecture

### Core Components
- **Domain-Driven Design**: Clear separation between business domains
- **Repository Pattern**: Data access abstraction through repositories
- **Service Layer**: Business logic encapsulation in service classes
- **DTO Pattern**: Data transfer objects for API communication

### Integration Patterns
- **Facade Pattern**: Unifies mapping and formatting operations
- **Strategy Pattern**: Different formatters for different field types
- **Factory Pattern**: Creates appropriate formatters dynamically
- **Open/Closed Principle**: Easy to extend without modifying existing code

### SOLID Principles
- **Single Responsibility**: Each class has one clear purpose
- **Open/Closed**: Extensible through interfaces and abstract classes
- **Liskov Substitution**: Proper inheritance hierarchies
- **Interface Segregation**: Focused, specific interfaces
- **Dependency Inversion**: Dependencies on abstractions, not concretions

## Recent Improvements

### Jira Integration Enhancements
- **Proper JSON Structure**: Correct Jira Document Format generation
- **204 Response Handling**: Proper handling of Jira API 204 No Content responses
- **Error Handling**: Comprehensive exception handling for API failures
- **Code Quality**: 43% code reduction through SOLID principles implementation

### Package Analysis
- **186 Classes Analyzed**: Comprehensive review of the entire package
- **10 Unused Classes Identified**: Opportunities for cleanup
- **Architecture Quality**: High adherence to SOLID principles
- **Maintainability**: Improved code organization and documentation

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
