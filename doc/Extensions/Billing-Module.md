# Billing Module

The billing module creates a bill with a quota and a set of ports. It
then tracks the use of these ports. It shows the use and the overage in
the bill.
Accounting works by total transferred data and by 95th percentile.

To enable and use the billing module, do these steps:

!!! setting "system/billing"
    ```bash
    lnms config:set enable_billing true
    ```

=== "Cron"
    Edit `/etc/cron.d/librenms` and add this line:
    ```bash
    */5 * * * * librenms /opt/librenms/poll-billing.php >> /dev/null 2>&1
    01  * * * * librenms /opt/librenms/billing-calculate.php >> /dev/null 2>&1
    ```

=== "Dispatcher Service"
    Go to Settings -> Poller -> Settings
    Then select `Billing Enabled` for each poller.

## Adding a bill

To create a new bill, from the LibreNMS menu select Ports -> Traffic Bills and
select `+ Create Bill`.

Enter the details in the form. Select at least one device and one port.

## Billing Nokia SAPs

On Nokia SR OS (TiMOS) devices, the customer handoff of a service is a SAP
(Service Access Point: port + encapsulation). Where most vendors model such a
handoff as a sub-interface that can be billed as a port, a Nokia SAP does not
appear as an interface in the ifTable and therefore cannot be billed as a
port. For those cases a bill can be based on individual SAPs: the bill then
accounts the ingress/egress octets of each selected SAP, using the same
`TIMETRA-SAP-MIB::sapBaseStatsTable` counters as the SAP traffic graphs.

To add a SAP to a bill, open the bill, select `Edit` and use the `Add SAP`
form. SAPs and ports can be mixed on the same bill; their usage is summed.
The MPLS discovery/poller module must be enabled on the device for SAPs to
be known.

## 95th Percentile Calculation

For 95th percentile billing, LibreNMS uses the higher of the input
calculation and the output calculation by default.

To use the total of the input and the output for the 95th percentile,
set 95th Calculation to "Aggregate". This setting applies to one bill.

!!! setting "system/billing"
    ```bash
    lnms config:set billing.95th_default_agg true
    ```

This configuration setting is cosmetic. It only changes the default
option of a new bill.