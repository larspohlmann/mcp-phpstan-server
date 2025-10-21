# MCP PHPStan Server (Local, No Dependencies)

A minimal Model Context Protocol (MCP) server implemented in pure PHP that exposes two tools:

- `phpstan_analyze`: Runs PHPStan and returns a readable report.
- `phpstan_pro`: Runs PHPStan Pro JSON output (if available) for richer diagnostics.

No Composer dependencies. Designed to be copied into any project.

## Structure (DDD)

- `src/Domain`: Tool contracts and results
- `src/Application`: JSON-RPC MCP server and registry
- `src/Infrastructure`: Process runner, config, tool implementations
- `bin/mcp-phpstan`: STDIO entrypoint

## Configure

You can configure via environment variables or `config/config.json`:

- `MCP_PHPSTAN_PATH` – path to `phpstan` binary (default: `vendor/bin/phpstan` then `phpstan` on PATH)
- `MCP_PHPSTAN_CONFIG` – path to a phpstan.neon or phpstan.neon.dist (optional; autodetected upward)
- `MCP_PHPSTAN_LEVEL` – level to pass to phpstan (e.g. `max` or `8`) if not set in config

If no config is provided, the server searches upwards from the current working directory for one of:
`phpstan.neon`, `phpstan.neon.dist`.

## Run

Mark the script executable once:

```bash
chmod +x mcp/phpstan-server/bin/mcp-phpstan
```

Then configure your MCP client to start the server via stdio using the command:

```bash
/absolute/path/to/mcp/phpstan-server/bin/mcp-phpstan
```

### Example: Claude Desktop config

```json
{
  "mcpServers": {
    "phpstan": {
      "command": "/absolute/path/to/mcp/phpstan-server/bin/mcp-phpstan",
      "env": {
        "MCP_PHPSTAN_PATH": "/usr/local/bin/phpstan",
        "MCP_PHPSTAN_CONFIG": "/path/to/your/phpstan.neon",
        "MCP_PHPSTAN_LEVEL": "max"
      }
    }
  }
}
```

The server speaks JSON-RPC 2.0 over STDIN/STDOUT and implements:
- `initialize`
- `tools/list`
- `tools/call`

## Tools

### phpstan_analyze
Input schema:

```json
{
  "type": "object",
  "properties": {
    "paths": {"type": "array", "items": {"type": "string"}},
    "level": {"type": ["string", "integer"], "description": "Override level"}
  },
  "required": ["paths"]
}
```

### phpstan_pro
Same input schema as `phpstan_analyze`, but returns JSON directly when possible.

## Notes
- Output to STDOUT is strictly JSON-RPC messages. Logs go to STDERR.
- Non-zero exit codes from `phpstan` may indicate findings as well as failures; we parse output to determine error state.
