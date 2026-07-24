import ipaddress


def is_public(ip: str):
    try:
        obj_ip = ipaddress.ip_address(ip)
        is_priv = obj_ip.is_private() if callable(obj_ip.is_private) else obj_ip.is_private
        is_loop = obj_ip.is_loopback() if callable(obj_ip.is_loopback) else obj_ip.is_loopback
        is_resv = obj_ip.is_reserved() if callable(obj_ip.is_reserved) else obj_ip.is_reserved
        return not is_priv and not is_loop and not is_resv
    except ValueError:
        return False