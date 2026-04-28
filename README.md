# PMRedstone

PMRedstone is a high-performance, standalone redstone simulation engine specifically engineered for PocketMine-MP 5. It provides a robust alternative to the core's limited redstone support, featuring a custom propagation logic and a budget-controlled update system.

## Core Features

- **Optimized Propagation**: Uses a custom Power Map and Dirty Queue system to handle complex circuits with minimal impact on server performance.
- **Budget Control**: Updates are distributed across ticks and worlds to prevent lag spikes.
- **Vanilla Accuracy**: Implements signal strength (0-15), vertical propagation, and complex component interactions.
- **Piston Engine (Beta)**: Custom block-moving physics with multi-block push chains and sticky retraction.
- **Extensible Architecture**: Clean API surface for developers to add custom redstone-powered components.

## Technical Documentation

For detailed information on architecture, component behavior, and development guides, please visit our official Wiki:

**https://github.com/vapebw/PMRedstone/wiki**

## Configuration Overview

The engine is highly configurable via `config.yml`. Below are the primary control segments:

```yaml
redstone:
  enabled: true
  tick-rate: 1
  max-update-budget: 2048

pistons:
  enabled: true
  max-push-distance: 12
  beta-mode: true
```

## Contributing

This project is open-source. Feel free to submit pull requests or report issues on the GitHub repository.

## License

PMRedstone is distributed as a free-to-use engine for the PocketMine-MP community. Redistribution for profit is strictly prohibited.