# Seguridad y manejo de API keys

1) Rotar la clave expuesta
- Ingrese a su cuenta OpenAI y REVOQUE la clave que fue publicada.
- Cree una nueva clave.

2) No dejar la clave en el repositorio
- Elimine cualquier archivo que contenga la clave:
  git rm --cached php/config.php
  echo "php/config.php" >> .gitignore
  git add .gitignore
  git commit -m "Remove sensitive config and ignore it"
  git push

3) Purgar la clave del historial Git
- Opción recomendada: usar BFG Repo-Cleaner (más simple) o git-filter-repo.

  Usando BFG:
  - Instale BFG (https://rtyley.github.io/bfg-repo-cleaner/)
  - Cree un archivo `replacements.txt` (local, no commitear) con la clave exacta:
      YOUR_OLD_KEY==>REMOVED_BY_BFG
  - Ejecute:
      git clone --mirror git@github.com:usuario/repo.git
      java -jar bfg.jar --replace-text replacements.txt repo.git
      cd repo.git
      git reflog expire --expire=now --all && git gc --prune=now --aggressive
      git push

  Usando git filter-repo:
  - git clone --mirror ...
  - git filter-repo --replace-text replacements.txt
  - git push

  IMPORTANTE: reemplace `YOUR_OLD_KEY` por la clave expuesta **solo en su máquina local**, no la suba al repo.

4) Desplegar la nueva clave de forma segura
- En producción, preferible usar variable de entorno:
  - Apache: SetEnv OPENAI_API_KEY "sk-..."
  - Nginx + php-fpm: export/Set env en el servicio o usar un archivo /etc/php/config separado no accesible vía web.
- Alternativa: crear php/config.php en el servidor (fuera del control de versiones) con:
  <?php define('OPENAI_API_KEY','sk-...');

5) Verificación final
- Asegúrese de que `php/config.php` ya no esté en el repo.
- Revise el historial remoto y confirme que la clave ya no aparece.
- Limite el acceso a los secretos: sólo admins del servidor deben poder ver la clave.

Si quieres, te doy los comandos exactos paso a paso para tu repositorio (BFG o git-filter-repo) y el ejemplo de cómo configurar la variable de entorno en XAMPP/Apache.
