# ecobat
Repository for Ecobat specific code.

Holds the Ecobat warehouse module which adds support for a separate reference
range dataset in the Warehouse database. This serves 2 purposes:

* Ensures that private reference range only records are available for reporting
  but are never accidentally released into the main occurrences table.
* Ensures an optimal structure for reference range reporting and analysis.
