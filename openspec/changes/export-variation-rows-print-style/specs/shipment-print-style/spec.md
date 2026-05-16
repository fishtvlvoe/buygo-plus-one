## ADDED Requirements

### Requirement: Print-optimized layout

The shipment detail page SHALL include @media print CSS rules that produce a clean, print-friendly layout when the user triggers browser print (Ctrl+P / Cmd+P).

#### Scenario: Hidden non-content elements during print

WHEN the user prints the shipment detail page
THEN the following elements SHALL be hidden:
  - Action buttons (export, print, mark-shipped buttons)
  - Navigation sidebar
  - Tab controls and page header navigation
  - Modal backdrop and close buttons
  - Merge toggle switch

#### Scenario: Visible content during print

WHEN the user prints the shipment detail page
THEN the following elements SHALL remain visible:
  - Shipment header information (shipment number, status, dates)
  - Customer information (name, phone, address)
  - Product detail table with all rows including sub-item variations
  - Order total

#### Scenario: Table formatting for print

WHEN the product detail table is printed
THEN the table SHALL span the full page width
AND table borders SHALL be visible for readability
AND font size SHALL be legible on paper (minimum 10pt equivalent)
