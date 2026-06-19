---
title: Troubleshooting
description: Common operational failures.
---

# Troubleshooting

| Symptom | Likely cause | Action |
| --- | --- | --- |
| No connection | Wrong IP, port, VLAN, firewall | Test TCP reachability from the PHP host. |
| Repeated NAK | LRC mode mismatch or malformed identifiers | Check `ECR17_LRC_MODE`, terminal id, and cash register id. |
| Payment timeout | Cardholder interaction exceeded timeout | Increase `response_timeout_ms` and run in a worker. |
| Exception after charge | TCP drop before response | Call `sendLastResult()` and reconcile. |
| Missing receipt lines | `receipt_drain_ms` too low or zero | Enable a small drain window if receipts arrive after result. |

::: callout danger "First response to payment uncertainty"
Do not retry the payment command. Recover the last terminal result.
:::
