# MCP PHPStan Server

> A [Model Context Protocol](https://modelcontextprotocol.io) server that brings **PHPStan** static analysis into your AI coding workflow — analyze PHP for bugs and type errors, straight from any MCP client.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![Dependencies](https://img.shields.io/badge/dependencies-none-brightgreen)
![License](https://img.shields.io/badge/license-MIT-blue)

Implemented in **pure PHP with zero Composer dependencies**. Drop it into any project or run it standalone — it speaks JSON-RPC 2.0 over stdio and works with Claude Desktop, Claude Code, Cursor, and any other MCP-compatible client.

## Tools

| Tool | Description |
| --- | --- |
| `phpstan_analyze` | Runs [PHPStan](https://phpstan.org) on one or more paths and returns a readable report of errors. |
| `phpstan_pro` | Runs PHPStan with JSON output for richer, structured diagnostics (works with [PHPStan Pro](https://phpstan.org/blog/introducing-phpstan-pro) when available). |

Both tools accept:

- `paths` (**required**) — array of absolute or project-relative paths to analyze
- `level` (optional) — override the configured rule level (e.g. `"max"` or `8`)

## Requirements

- **PHP 8.1+**
- A `phpstan` binary available (project `vendor/bin/phpstan` or a global install)

## Quick start

1. Clone the repository (or copy it into your project):

   ```bash
   git clone https://github.com/larspohlmann/mcp-phpstan-server.git
   ```

2. Make the entrypoint executable:

   ```bash
   chmod +x mcp-phpstan-server/bin/mcp-phpstan
   ```

3. Register it with your MCP client (see below).

## Configuration

Configure via environment variables **or** `config/config.json`. Environment variables take precedence.

| Variable | `config.json` key | Description | Default |
| --- | --- | --- | --- |
| `MCP_PHPSTAN_PATH` | `phpstanPath` | Path to the `phpstan` binary | `vendor/bin/phpstan`, then `phpstan` on `PATH` |
| `MCP_PHPSTAN_CONFIG` | `phpstanConfig` | Path to a `phpstan.neon` / `phpstan.neon.dist` | auto-discovered |
| `MCP_PHPSTAN_LEVEL` | `phpstanLevel` | Rule level to analyze at (e.g. `max` or `8`) | `max` |
| `MCP_PHPSTAN_MEMORY_LIMIT` | `phpstanMemoryLimit` | Value for PHPStan `--memory-limit` (e.g. `1G`) | `1G` |

If no config path is provided, the server searches upward from the current working directory for `phpstan.neon` or `phpstan.neon.dist`.

## Client setup

### Claude Desktop / Claude Code

Add the server to your MCP config:

```json
{
  "mcpServers": {
    "phpstan": {
      "command": "/absolute/path/to/mcp-phpstan-server/bin/mcp-phpstan",
      "env": {
        "MCP_PHPSTAN_PATH": "/usr/local/bin/phpstan",
        "MCP_PHPSTAN_CONFIG": "/path/to/your/phpstan.neon",
        "MCP_PHPSTAN_LEVEL": "max",
        "MCP_PHPSTAN_MEMORY_LIMIT": "1G"
      }
    }
  }
}
```

Then ask your assistant something like *"Run PHPStan on the src directory and explain the errors."*

## Example tool call

```json
{
  "name": "phpstan_analyze",
  "arguments": {
    "paths": ["src", "tests"],
    "level": "max"
  }
}
```

## Architecture

The server follows a light Domain-Driven Design layout:

- `src/Domain` — tool contracts and result value objects
- `src/Application` — JSON-RPC MCP server and tool registry
- `src/Infrastructure` — process runner, config, tool implementations
- `bin/mcp-phpstan` — stdio entrypoint

It implements the MCP methods `initialize`, `tools/list`, and `tools/call`.

## Notes

- STDOUT carries **only** JSON-RPC messages; all logs go to STDERR.
- A non-zero exit code from `phpstan` can mean findings as well as failures — the server parses the output to determine the error state.

## License

[MIT](LICENSE)
