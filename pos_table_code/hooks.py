from odoo import SUPERUSER_ID, api


FLOOR_FORM_CANDIDATES = [
    """
    <data>
        <xpath expr="//field[@name='table_ids']/tree/field[@name='table_number']" position="after">
            <field name="table_code"/>
        </xpath>
    </data>
    """,
    """
    <data>
        <xpath expr="//field[@name='table_ids']/list/field[@name='table_number']" position="after">
            <field name="table_code"/>
        </xpath>
    </data>
    """,
    """
    <data>
        <xpath expr="//field[@name='restaurant_table_ids']/tree/field[@name='table_number']" position="after">
            <field name="table_code"/>
        </xpath>
    </data>
    """,
    """
    <data>
        <xpath expr="//field[@name='restaurant_table_ids']/list/field[@name='table_number']" position="after">
            <field name="table_code"/>
        </xpath>
    </data>
    """,
]

TABLE_FORM_CANDIDATES = [
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


def _create_extension_view(env, parent_view, model_name, arch_candidates, extension_name):
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
                    "model": model_name,
                    "type": parent_view.type,
                    "mode": "extension",
                    "inherit_id": parent_view.id,
                    "arch": arch,
                })
        except Exception:
            continue
    return False


def _resolve_env(*args):
    if len(args) == 1 and hasattr(args[0], "cr"):
        return args[0]
    if len(args) >= 1:
        return api.Environment(args[0], SUPERUSER_ID, {})
    raise ValueError("Unsupported post_init_hook signature")


def _find_form_view(view_model, model_name):
    return view_model.search([
        ("model", "=", model_name),
        ("type", "=", "form"),
    ], order="priority, id", limit=1)


def ensure_dynamic_views(env):
    view_model = env["ir.ui.view"].sudo()

    floor_form = _find_form_view(view_model, "restaurant.floor")
    if floor_form:
        _create_extension_view(
            env,
            floor_form,
            "restaurant.floor",
            FLOOR_FORM_CANDIDATES,
            "pos.table.code.floor.form.extension",
        )

    table_form = _find_form_view(view_model, "restaurant.table")
    if table_form:
        _create_extension_view(
            env,
            table_form,
            "restaurant.table",
            TABLE_FORM_CANDIDATES,
            "pos.table.code.table.form.extension",
        )


def post_init_hook(*args):
    env = _resolve_env(*args)
    ensure_dynamic_views(env)
