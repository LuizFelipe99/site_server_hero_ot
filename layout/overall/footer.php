				</div>

				<?php include 'layout/aside.php'; ?>
			</div>
		</div>

		<footer class="site-footer">
			<div class="footer-inner">
				<div class="footer-brand">
					<span class="footer-title"><?php echo $layout_server_name; ?></span>
					<p>&copy; <?php echo date('Y') . ' ' . $config['site_title']; ?>. Engine: <a href="credits.php">Znote AAC</a>.</p>
				</div>
				<div class="footer-meta">
					<p><i class="fa fa-clock-o"></i> Horário do servidor: <?php echo getClock(false, true); ?></p>
					<p class="footer-muted"><?php echo 'Página gerada em ' . elapsedTime() . 's · ' . $aacQueries . ' consultas'; ?></p>
				</div>
			</div>
		</footer>
	</body>
</html>
