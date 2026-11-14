#!/bin/sh
# Préparation initiale du VPS Ubuntu 24.04 (exécuté une fois en root, idempotent).
# Docker officiel, pare-feu, fail2ban, mises à jour de sécurité automatiques,
# utilisateur de déploiement sans mot de passe et connexion SSH par clé uniquement.
set -eu
export DEBIAN_FRONTEND=noninteractive
DEPLOY_USER=${DEPLOY_USER:-deploy}

apt-get update -q
apt-get -y -q upgrade
apt-get install -y -q ca-certificates curl gnupg ufw fail2ban unattended-upgrades python3 git

# Docker Engine depuis le dépôt officiel Docker (https://docs.docker.com/engine/install/ubuntu/).
if ! command -v docker >/dev/null 2>&1; then
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  . /etc/os-release
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${UBUNTU_CODENAME:-$VERSION_CODENAME} stable" > /etc/apt/sources.list.d/docker.list
  apt-get update -q
  apt-get install -y -q docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

# Journaux Docker limités pour ne pas remplir le disque.
if [ ! -f /etc/docker/daemon.json ]; then
  printf '{\n  "log-driver": "json-file",\n  "log-opts": {"max-size": "10m", "max-file": "3"}\n}\n' > /etc/docker/daemon.json
  systemctl restart docker
fi

# Pare-feu : seuls SSH, HTTP et HTTPS sont ouverts. Les bases ne publient aucun port.
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

# Mises à jour de sécurité automatiques et blocage des tentatives SSH répétées.
dpkg-reconfigure -f noninteractive unattended-upgrades
systemctl enable --now fail2ban

# Utilisateur de déploiement : membre du groupe docker, mêmes clés autorisées que root.
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
  adduser --disabled-password --gecos '' "$DEPLOY_USER"
fi
usermod -aG docker "$DEPLOY_USER"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
cat /root/.ssh/authorized_keys > "/home/$DEPLOY_USER/.ssh/authorized_keys"
chown "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh/authorized_keys"
chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"
install -d -m 750 -o "$DEPLOY_USER" -g "$DEPLOY_USER" /opt/innovevents

# SSH : clé uniquement, root sans mot de passe. La console web Hostinger reste un recours.
cat > /etc/ssh/sshd_config.d/10-innovevents.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
EOF
sshd -t && systemctl reload ssh

echo "Serveur prêt : Docker $(docker --version | cut -d' ' -f3 | tr -d ,), pare-feu actif, utilisateur $DEPLOY_USER."
