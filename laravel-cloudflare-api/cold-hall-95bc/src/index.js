export default {
	async fetch(request, env, ctx) {
	  const url = new URL(request.url);
	  const pathname = url.pathname;

	  // Serve static assets like index.html
	  const assetResponse = await env.ASSETS.fetch(request);
	  if (assetResponse.status !== 404) {
		return assetResponse;
	  }

	  // Custom endpoints
	  if (pathname === '/message') {
		return new Response('👋 Hello from your Worker!', {
		  headers: { 'Content-Type': 'text/plain' },
		});
	  }

	  if (pathname === '/random') {
		const uuid = crypto.randomUUID();
		return new Response(uuid, {
		  headers: { 'Content-Type': 'text/plain' },
		});
	  }

	  // Fallback 404
	  return new Response('Not Found', { status: 404 });
	}
  };
