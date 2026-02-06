from odoo import SUPERUSER_ID, api


FORM_ARCH_CANDIDATES = [
    """
    <data>
        <xpath expr="//field[@name='name']" position="after">
            <field name="table_code" placeholder="e.g. Family Booth"/>
        </xpath>
    </data>
    """,
    """
    <data>
        <xpath expr="//form//sheet//group[1]" position="inside">
            <field name="table_code" placeholder="e.g. Family Booth"/>
        </xpath>
    </data>
    """,
]

TREE_ARCH_CANDIDATES = [
    """
    <data>
        <xpath expr="//field[@name='name']" position="after">
            <field name="table_code" optional="show"/>
        </xpath>
    </data>
    """,
    """
    <data>
        <xpath expr="//tree" position="inside">
            <field name="table_code" optional="show"/>
        </xpath>
    </data>
    """,
]


def _create_extension_view(env, parent_view, arch_candidates, extension_name):
    view_model = env["ir.ui.view"].sudo()
    existing = view_model.search([
        ("name", "=", extension_name),
        ("inherit_id", "=", parent_view.id),
    ], limit=1)
    if existing:
        return existing

    for arch in arch_candidates:
        try:
            with env.cr.savepoint():
                return view_model.create({
                    "name": extension_name,
                    "model": "restaurant.table",
                    "type": parent_view.type,
                    "mode": "extension",
                    "inherit_id": parent_view.id,
                    "arch": arch,
                })
        except Exception:
            continue
    return False


def _resolve_env(*args):
    # Odoo variants may call post_init_hook with (env) or (cr, registry).
    if len(args) == 1 and hasattr(args[0], "cr"):
        return args[0]
    if len(args) >= 1:
        return api.Environment(args[0], SUPERUSER_ID, {})
    raise ValueError("Unsupported post_init_hook signature")


def post_init_hook(*args):
    env = _resolve_env(*args)
    view_model = env["ir.ui.view"].sudo()

    form_parent = view_model.search([
        ("model", "=", "restaurant.table"),
        ("type", "=", "form"),
    ], order="priority, id", limit=1)

    tree_parent = view_model.search([
        ("model", "=", "restaurant.table"),
        ("type", "=", "tree"),
    ], order="priority, id", limit=1)

    if form_parent:
        _create_extension_view(
            env,
            form_parent,
            FORM_ARCH_CANDIDATES,
            "pos.table.code.form.extension",
        )

    if tree_parent:
        _create_extension_view(
            env,
            tree_parent,
            TREE_ARCH_CANDIDATES,
            "pos.table.code.tree.extension",
        )
