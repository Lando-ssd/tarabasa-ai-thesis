import f01_system_flow, flows_learner, flows_roles, arch, fdd, erd
warns = []
warns += f01_system_flow.build()
for fn in (flows_learner.l1, flows_learner.l2, flows_learner.l3):
    warns += fn()
for fn in (flows_roles.t1, flows_roles.t2, flows_roles.t3, flows_roles.t4, flows_roles.p1, flows_roles.p2, flows_roles.a1):
    warns += fn()
for fn in (arch.f02_architecture, arch.f03_network_model, arch.f04_network_topology, arch.f15_rule_chain):
    warns += fn()
for r in fdd.build_all():
    warns += r
warns += erd.erd()
print("WARNINGS:", warns)
